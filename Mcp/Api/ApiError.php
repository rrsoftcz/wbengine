<?php

namespace Wbengine\Mcp\Api;

/** A failed call to the site: the code the API gave (or a local one) with advice for the model. */
final class ApiError extends \RuntimeException
{
    /** Local codes: transport, redirect_refused, response_too_large, bad_response, invalid_configuration. */
    private const ADVICE = [
        'unauthorized' => 'The integration key was rejected. Ask the site administrator for a valid key in WBENGINE_MCP_KEY.',
        'forbidden' => 'The key does not allow this. The administrator can add the missing permission group to the key on the Integrations page.',
        'ip_not_allowed' => 'The key is restricted to other addresses than this machine. Issue a key without an address restriction for use from a laptop, or add this address to the key.',
        'precondition_failed' => 'The article changed since it was read. Call get_article again, merge your change into the current content and retry with the new etag.',
        'precondition_required' => 'The current etag is needed. Call get_article first.',
        'article_site_conflict' => 'That page already has an article. Choose another page or create a new one with new_page.',
        'missing_site' => 'The article has no page yet. Pass page_id or new_page when saving the draft.',
        'no_draft' => 'The article has no draft to publish. Save a draft first.',
        'hide_unavailable' => 'This site cannot hide articles yet: its database needs the update that adds the hidden flag. Ask the administrator to run the pending migrations.',
        'duplicate_link' => 'A page with that link exists already. Choose a different link.',
        'invalid_link' => 'The page link is not valid. Use lowercase letters, digits and hyphens, for example /blog/my-post/.',
        'invalid_field' => 'A field was not accepted by the site. Check the tool input.',
        'not_found' => 'It does not exist on this site. Use list_articles or list_pages to find the right id.',
        'analytics_disabled' => 'Statistics are not switched on for this site. The administrator enables analytics in the site settings.',
        'invalid_period' => 'That period is not available: it is in the future, or older than the site keeps statistics. Ask for fewer days or a more recent period.',
        'invalid_date' => 'A date must be written YYYY-MM-DD.',
        'invalid_locale' => 'The language must be a locale such as en or cs.',
        'locale_unavailable' => 'Views are not split by language on this site yet. The administrator applies the 6.7.0 database update first.',
        'invalid_filter' => 'A filter was not accepted by the site. Check the tool input.',
        'rate_limited' => 'The site is limiting requests. Wait a minute and retry.',
        'unavailable' => 'The API is switched off or not ready on this site.',
        'transport' => 'The site could not be reached. Check WBENGINE_MCP_URL and the network.',
        'redirect_refused' => 'The site answered with a redirect, which is not followed. Check that WBENGINE_MCP_URL is the final HTTPS address of the site.',
        'response_too_large' => 'The answer was larger than the limit and was dropped. Ask for less.',
        'bad_response' => 'The site did not answer like the WBengine API v2. Check WBENGINE_MCP_URL.',
    ];

    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }

    /** Text for the model: what happened and what to do. */
    public function advice(): string
    {
        $hint = self::ADVICE[$this->errorCode] ?? null;
        $detail = '' !== $this->getMessage() ? $this->getMessage() : $this->errorCode;
        return null === $hint ? 'The site refused the request (' . $this->errorCode . '): ' . $detail : $hint . ' (' . $this->errorCode . ': ' . $detail . ')';
    }
}
