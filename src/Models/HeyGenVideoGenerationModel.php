<?php

declare(strict_types=1);

namespace HeyGen\Models;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Providers\Models\VideoGeneration\Contracts\VideoGenerationModelInterface;
use HeyGen\Authentication\HeyGenApiKeyRequestAuthentication;
use HeyGen\Metadata\HeyGenModelMetadataDirectory;
use HeyGen\Provider\HeyGen;

/**
 * Model class for HeyGen video generation.
 *
 * Supports two generation paths:
 *  - `heygen-video-agent` model ID: uses the Video Agent API, which automatically selects an
 *    avatar and voice from the prompt. This is the recommended default.
 *  - Any avatar model ID: uses the Avatar Video API. A `voice_id` may optionally be provided
 *    via the model config's `customOptions` array.
 *
 * Video generation is asynchronous. After submission, the model polls the HeyGen status
 * endpoint until the video is ready or a 5-minute timeout is reached. The result contains
 * the URL of the completed video as its text content.
 *
 * @since 1.0.0
 */
class HeyGenVideoGenerationModel extends AbstractApiBasedModel implements VideoGenerationModelInterface {


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
	 * Generates a video from the given prompt messages.
	 *
	 * The text content of all prompt messages is concatenated to form the script. The
	 * generation path is determined by the model ID: `heygen-video-agent` uses the Video
	 * Agent API; all other IDs are treated as avatar IDs and use the Avatar Video API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt Array of Message objects forming the generation prompt.
	 * @return GenerativeAiResult Result containing the completed video URL as text content.
	 */
	public function generateVideoResult( array $prompt ): GenerativeAiResult {
		$script  = $this->extractScript( $prompt );
		$modelId = $this->metadata()->getId();

		if ( $modelId === HeyGenModelMetadataDirectory::VIDEO_AGENT_MODEL_ID ) {
			return $this->generateWithVideoAgent( $script );
		}

		return $this->generateWithAvatarApi( $script, $modelId );
	}

	/**
	 * Extracts a plain-text script from an array of prompt messages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt Array of Message objects.
	 * @return string Concatenated text from all text parts.
	 */
	private function extractScript( array $prompt ): string {
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

	/**
	 * Submits a video generation job using HeyGen's Video Agent API.
	 *
	 * Returns immediately with the video ID. Use the video ID to check status
	 * via the HeyGen status endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $script The text prompt/script to generate a video from.
	 * @return GenerativeAiResult Result containing the video ID as text content.
	 * @throws RuntimeException If the API does not return a video ID.
	 */
	private function generateWithVideoAgent( string $script ): GenerativeAiResult {
		$request = new Request(
			HttpMethodEnum::post(),
			HeyGen::url( '/v1/video_agent/generate' ),
			[ 'Content-Type' => 'application/json' ],
			[ 'prompt' => $script ]
		);

		$response     = $this->sendRequest( $request );
		$responseData = $response->getData();

		if ( empty( $responseData['data']['video_id'] ) ) {
			error_log( '[hey-gen] Video Agent response: ' . wp_json_encode( $responseData ) );
			throw new RuntimeException( 'HeyGen Video Agent did not return a video ID.' );
		}

		return $this->buildVideoResult( $responseData['data']['video_id'] );
	}

	/**
	 * Generates a video using HeyGen's Avatar Video API.
	 *
	 * Uses the given avatar ID and, optionally, a voice ID from the model config's
	 * `customOptions` array (`customOptions['voice_id']`).
	 *
	 * @since 1.0.0
	 *
	 * @param string $script   The script text for the avatar to speak.
	 * @param string $avatarId The HeyGen avatar ID to use.
	 * @return GenerativeAiResult Result containing the completed video URL.
	 * @throws RuntimeException If the API does not return a video ID or generation fails.
	 */
	private function generateWithAvatarApi( string $script, string $avatarId ): GenerativeAiResult {
		$customOptions = $this->getConfig()->getCustomOptions() ?? [];

		$voiceParams = [
			'type'       => 'text',
			'input_text' => $script,
		];

		if ( ! empty( $customOptions['voice_id'] ) ) {
			$voiceParams['voice_id'] = (string) $customOptions['voice_id'];
		}

		$params = [
			'video_inputs' => [
				[
					'character'  => [
						'type'         => 'avatar',
						'avatar_id'    => $avatarId,
						'avatar_style' => 'normal',
					],
					'voice'      => $voiceParams,
					'background' => [
						'type'  => 'color',
						'value' => '#FAFAFA',
					],
				],
			],
			'caption'      => false,
		];

		$request = new Request(
			HttpMethodEnum::post(),
			HeyGen::url( '/v2/video/generate' ),
			[ 'Content-Type' => 'application/json' ],
			$params
		);

		$response     = $this->sendRequest( $request );
		$responseData = $response->getData();

		if ( empty( $responseData['data']['video_id'] ) ) {
			throw new RuntimeException( 'HeyGen did not return a video ID.' );
		}

		return $this->buildVideoResult( $responseData['data']['video_id'] );
	}

	/**
	 * Authenticates and sends an HTTP request using the model's transporter.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The unauthenticated request to send.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response The API response.
	 */
	private function sendRequest( Request $request ): \WordPress\AiClient\Providers\Http\DTO\Response {
		$authenticatedRequest = $this->getRequestAuthentication()->authenticateRequest( $request );
		return $this->getHttpTransporter()->send( $authenticatedRequest );
	}

	/**
	 * Builds a GenerativeAiResult containing the video ID as text content.
	 *
	 * The video ID is surfaced as the text content of the result so callers can
	 * retrieve it via `$result->toText()` and use it to check status separately.
	 *
	 * @since 1.0.0
	 *
	 * @param string $videoId The HeyGen video ID.
	 * @return GenerativeAiResult The constructed result.
	 */
	private function buildVideoResult( string $videoId ): GenerativeAiResult {
		$message   = new ModelMessage( [ new MessagePart( $videoId ) ] );
		$candidate = new Candidate( $message, FinishReasonEnum::stop() );
		$tokenUsage = new TokenUsage( 0, 0, 0 );

		return new GenerativeAiResult(
			$videoId,
			[ $candidate ],
			$tokenUsage,
			$this->providerMetadata(),
			$this->metadata()
		);
	}
}
