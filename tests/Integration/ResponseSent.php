<?php

namespace Wbengine\Tests\Integration;

/**
 * Thrown instead of ending the request, so a test can look at the API response.
 */
class ResponseSent extends \Exception
{
}
