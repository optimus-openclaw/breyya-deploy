<?php
/**
 * Content Inventory Builder - build-inventory.php
 * Generates a JSON catalog of available PPV content from Cloudflare R2
 * 
 * Usage:
 *   php build-inventory.php                    # Use local rclone (Mac Mini only)
 *   php build-inventory.php --from-json FILE   # Use pre-generated listing
 */

$DB_PATH = __DIR__ . '/../../data/breyya.db';
$OUTPUT_FILE = __DIR__ . '/../../data/content-inventory.json';
$R2_PUBLIC_BASE = 'https://pub-24f8d05ca30745b496a897793321ddf1.r2.dev';

// Parse command line arguments
$fromJsonFile = null;
foreach ($argv as $i => $arg) {
    if ($arg === '--from-json' && isset($argv[$i + 1])) {
        $fromJsonFile = $argv[$i + 1];
        break;
    }
}

function convertOutfitName($folderName) {
    // Convert "pink-bra-cream-cardigan" to "pink bra with cream cardigan"
    $parts = explode('-', $folderName);
    if (count($parts) <= 2) {
        return str_replace('-', ' ', $folderName);
    }
    
    // Find natural breaks for "with" insertion
    $result = [];
    $currentGroup = [];
    
    foreach ($parts as $part) {
        $currentGroup[] = $part;
        
        // If we hit clothing keywords, create a group
        if (in_array($part, ['bra', 'top', 'dress', 'skirt', 'pants', 'jeans', 'shorts', 'cardigan', 'sweater', 'hoodie', 'jacket'])) {
            $result[] = implode(' ', $currentGroup);
            $currentGroup = [];
        }
    }
    
    // Add any remaining parts
    if (!empty($currentGroup)) {
        $result[] = implode(' ', $currentGroup);
    }
    
    // Join groups with "with" or just spaces
    if (count($result) > 1) {
        return implode(' with ', $result);
    } else {
        return implode(' ', $parts);
    }
}

function getTierFromPath($path) {
    if (strpos($path, 'ppv-tier1/') !== false || strpos($path, 'PPV Tier 1') !== false) {
        return 1;
    }
    if (strpos($path, 'ppv-tier2/') !== false || strpos($path, 'PPV Tier 2') !== false) {
        return 2;
    }
    if (strpos($path, 'ppv-video/') !== false) {
        return 3; // Videos are whale-only
    }
    return 1; // Default to Tier 1
}

function getTypeFromPath($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'mov', 'avi'])) {
        return 'video';
    }
    return 'photo';
}

function getOutfitFromPath($path) {
    // Extract outfit name from various path patterns
    if (preg_match('#/([^/]+)/images?/[^/]+$#', $path, $matches)) {
        return $matches[1];
    }
    if (preg_match('#/([^/]+)/[^/]+\.(jpg|jpeg|png|mp4|mov)$#', $path, $matches)) {
        return $matches[1];
    }
    // Fallback: use parent directory
    return basename(dirname($path));
}

echo "🎬 Building PPV content inventory...\n";

// Get R2 file listing
$files = [];

if ($fromJsonFile) {
    echo "📁 Reading from JSON file: $fromJsonFile\n";
    if (!file_exists($fromJsonFile)) {
        die("❌ Error: File not found: $fromJsonFile\n");
    }
    $content = file_get_contents($fromJsonFile);
    $files = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("❌ Error: Invalid JSON in $fromJsonFile\n");
    }
} else {
    echo "☁️  Fetching from R2 via rclone...\n";
    $command = 'rclone lsjson r2:breyya-content/ --recursive';
    $output = shell_exec($command);
    if (empty($output)) {
        die("❌ Error: rclone command failed or returned empty result\n");
    }
    $files = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("❌ Error: Invalid JSON from rclone\n");
    }
}

echo "📊 Processing " . count($files) . " files...\n";

$inventory = [
    'updated_at' => date('c'),
    'items' => [],
    'stats' => [
        'tier1_photos' => 0,
        'tier2_photos' => 0,
        'videos' => 0,
        'total_files' => 0
    ]
];

$itemId = 1;

foreach ($files as $file) {
    if ($file['IsDir']) continue; // Skip directories
    
    $path = $file['Path'];
    $size = $file['Size'] ?? 0;
    
    // Skip tiny files (likely thumbnails or system files)
    if ($size < 50000) continue; // 50KB minimum
    
    // Only include PPV content (skip other directories)
    if (!preg_match('#(ppv-tier[12]|PPV Tier [12]|ppv-video)/#', $path)) {
        continue;
    }
    
    $tier = getTierFromPath($path);
    $type = getTypeFromPath($path);
    $outfitRaw = getOutfitFromPath($path);
    $description = convertOutfitName($outfitRaw);
    $publicUrl = $R2_PUBLIC_BASE . '/' . $path;
    
    $item = [
        'id' => 'ppv-' . str_pad($itemId, 3, '0', STR_PAD_LEFT),
        'key' => $path,
        'tier' => $tier,
        'type' => $type,
        'outfit' => $outfitRaw,
        'public_url' => $publicUrl,
        'description' => $description,
        'size' => $size
    ];
    
    $inventory['items'][] = $item;
    
    // Update stats
    if ($type === 'video') {
        $inventory['stats']['videos']++;
    } elseif ($tier === 1) {
        $inventory['stats']['tier1_photos']++;
    } elseif ($tier === 2) {
        $inventory['stats']['tier2_photos']++;
    }
    $inventory['stats']['total_files']++;
    
    $itemId++;
}

// Create data directory if it doesn't exist
$dataDir = dirname($OUTPUT_FILE);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Write inventory file
$json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($OUTPUT_FILE, $json);

echo "✅ Inventory complete!\n";
echo "📝 Items: " . count($inventory['items']) . "\n";
echo "📊 Stats:\n";
echo "   - Tier 1 Photos: " . $inventory['stats']['tier1_photos'] . "\n";
echo "   - Tier 2 Photos: " . $inventory['stats']['tier2_photos'] . "\n";
echo "   - Videos: " . $inventory['stats']['videos'] . "\n";
echo "💾 Saved to: $OUTPUT_FILE\n";

?>