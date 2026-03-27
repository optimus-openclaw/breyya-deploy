# Anthropic API Rate Limits - Breyya.com

## Current Implementation
- **Rate Limit**: 45 requests per minute (conservative)
- **Tracking**: File-based counter in `data/.api-rate-limit`
- **Behavior**: When limit approached, messages are rescheduled for +1 minute
- **Logging**: Rate limit hits logged in go.php debug output

## Anthropic Rate Limits by Tier

### Build Tier (Default for new accounts)
- **Rate**: 50 requests per minute
- **Monthly**: Up to 10,000 requests
- **Cost**: $0.003 per 1K input tokens, $0.015 per 1K output tokens

### Scale Tier (After usage history)
- **Rate**: 1,000 requests per minute  
- **Monthly**: Higher limits based on usage
- **Cost**: Same as Build tier

### Our Configuration
- **Current Setting**: 45 req/min (90% of Build tier limit)
- **Model**: claude-sonnet-4-20250514
- **Prompt Caching**: Enabled (saves ~50% on input tokens)
- **Fallback**: claude-haiku-3-20240307 (if primary fails)

## Monitoring
Check rate limit status:
```bash
curl "https://breyya.com/api/chat/go.php?secret=breyya-chat-cron-2026"
```
Look for `rate_limit_hit` in debug output.

## Capacity Analysis
- **45 req/min** = **2,700 req/hour** = **64,800 req/day**
- With current message volume (~50-100 messages/day), we're well within limits
- Rate limiting will only engage during high-traffic periods or load tests

## Cost with Prompt Caching
- **System Prompt**: ~19K tokens (cached for 5 minutes)
- **Cached Cost**: ~50% reduction on repeated calls
- **Estimated Monthly**: $50-150 depending on volume

## Upgrade Triggers
Consider upgrading to Scale tier when:
1. Consistently hitting 45 req/min limit
2. Monthly volume exceeds 8,000 requests  
3. Need for higher burst capacity during peak times