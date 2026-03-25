<?php

declare(strict_types=1);

namespace HeyGen\Authentication;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Class for HTTP request authentication using an API key in a HeyGen API compliant way.
 *
 * HeyGen uses the `X-Api-Key` header for authentication rather than the standard
 * `Authorization: Bearer` pattern.
 *
 * @since 1.0.0
 */
class HeyGenApiKeyRequestAuthentication extends ApiKeyRequestAuthentication {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function authenticateRequest( Request $request ): Request {
		return $request->withHeader( 'X-Api-Key', $this->apiKey );
	}
}
