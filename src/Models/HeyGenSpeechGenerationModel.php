<?php

declare(strict_types=1);

namespace HeyGen\Models;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Providers\Models\SpeechGeneration\Contracts\SpeechGenerationModelInterface;
use HeyGen\Authentication\HeyGenApiKeyRequestAuthentication;
use HeyGen\Provider\HeyGen;

/**
 * Model class for HeyGen text-to-speech generation.
 *
 * Calls the HeyGen `/v1/audio/text_to_speech` endpoint and returns
 * a GenerativeAiResult containing the audio URL as a File message part.
 *
 * @since 1.0.0
 */
class HeyGenSpeechGenerationModel extends AbstractApiBasedModel implements SpeechGenerationModelInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function getRequestAuthentication(): RequestAuthenticationInterface {
		$auth = parent::getRequestAuthentication();
		if ( ! $auth instanceof ApiKeyRequestAuthentication ) {
			return $auth;
		}
		return new HeyGenApiKeyRequestAuthentication( $auth->getApiKey() );
	}

	/**
	 * Generates speech from the given prompt messages.
	 *
	 * The text content of all prompt messages is concatenated to form the
	 * input text. Optionally, a `voice_id` and `speed` may be provided via
	 * the model config's `customOptions` array.
	 *
	 * Uses wp_remote_post() directly to allow a longer timeout for large inputs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt Array of Message objects forming the generation prompt.
	 * @return GenerativeAiResult Result containing the audio URL as a File message part.
	 * @throws RuntimeException If the API does not return an audio URL.
	 */
	public function generateSpeechResult( array $prompt ): GenerativeAiResult {
		$text          = $this->extractText( $prompt );
		$customOptions = $this->getConfig()->getCustomOptions() ?? [];

		$params = [
			'text' => $text,
		];

		if ( ! empty( $customOptions['voice_id'] ) ) {
			$params['voice_id'] = (string) $customOptions['voice_id'];
		}

		if ( isset( $customOptions['speed'] ) ) {
			$params['speed'] = (float) $customOptions['speed'];
		}

		$request = new Request(
			HttpMethodEnum::post(),
			HeyGen::url( '/v1/audio/text_to_speech' ),
			[ 'Content-Type' => 'application/json' ],
			$params
		);

		$options = new RequestOptions();
		$options->setTimeout( 300.0 );

		$auth                 = $this->getRequestAuthentication();
		$authenticatedRequest = $auth->authenticateRequest( $request );
		$response             = $this->getHttpTransporter()->send( $authenticatedRequest, $options );
		$responseData         = $response->getData();

		if ( empty( $responseData['data']['audio_url'] ) ) {
			error_log( '[hey-gen] TTS response: ' . wp_json_encode( $responseData ) );
			throw new RuntimeException( 'HeyGen did not return an audio URL.' );
		}

		$audioUrl = $responseData['data']['audio_url'];
		$file     = new File( $audioUrl, 'audio/mpeg' );
		$part     = new MessagePart( $file );
		$message  = new ModelMessage( [ $part ] );
		$candidate = new Candidate( $message, FinishReasonEnum::stop() );
		$tokenUsage = new TokenUsage( 0, 0, 0 );

		return new GenerativeAiResult(
			$audioUrl,
			[ $candidate ],
			$tokenUsage,
			$this->providerMetadata(),
			$this->metadata()
		);
	}

	/**
	 * Extracts plain text from an array of prompt messages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt Array of Message objects.
	 * @return string Concatenated text from all text parts.
	 */
	private function extractText( array $prompt ): string {
		$parts = [];

		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				if ( $part instanceof MessagePart && $part->getType()->isText() ) {
					$parts[] = (string) $part->getText();
				}
			}
		}

		return implode( ' ', $parts );
	}
}
