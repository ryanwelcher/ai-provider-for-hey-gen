<?php

declare(strict_types=1);

namespace HeyGen\Metadata;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use HeyGen\Authentication\HeyGenApiKeyRequestAuthentication;
use HeyGen\Provider\HeyGen;

/**
 * Class for the HeyGen model metadata directory.
 *
 * HeyGen "models" correspond to its available avatars. The special `heygen-video-agent`
 * model is always included as the recommended default and uses HeyGen's Video Agent API,
 * which selects an avatar automatically from the text prompt.
 *
 * @since 1.0.0
 *
 * @phpstan-type AvatarsResponseData array{
 *     error: null|string,
 *     data: array{
 *         avatars: list<array{avatar_id: string, avatar_name: string, gender?: string}>,
 *         talking_photos?: list<mixed>
 *     }
 * }
 */
class HeyGenModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * The model ID for the HeyGen Video Agent, which generates a video automatically from a prompt.
	 *
	 * @since 1.0.0
	 */
	public const VIDEO_AGENT_MODEL_ID = 'heygen-video-agent';

	/**
	 * The model ID for the HeyGen text-to-speech model.
	 *
	 * @since 1.0.0
	 */
	public const TTS_MODEL_ID = 'heygen-tts';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function getRequestAuthentication(): RequestAuthenticationInterface {
		$requestAuthentication = parent::getRequestAuthentication();
		if ( ! $requestAuthentication instanceof ApiKeyRequestAuthentication ) {
			return $requestAuthentication;
		}
		return new HeyGenApiKeyRequestAuthentication( $requestAuthentication->getApiKey() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Overrides the default OpenAI-compatible models endpoint to use HeyGen's avatars endpoint,
	 * which serves as the model listing for this provider.
	 *
	 * @since 1.0.0
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		return new Request(
			HttpMethodEnum::get(),
			HeyGen::url( '/v2/avatars' ),
			$headers,
			null
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var AvatarsResponseData $responseData */
		$responseData = $response->getData();

		if ( ! isset( $responseData['data'] ) || ! is_array( $responseData['data'] ) ) {
			throw ResponseException::fromMissingData( 'HeyGen', 'data' );
		}

		$videoCapabilities = [
			CapabilityEnum::videoGeneration(),
		];

		$videoOptions = [
			new SupportedOption( OptionEnum::customOptions() ),
		];

		$speechCapabilities = [
			CapabilityEnum::speechGeneration(),
		];

		$speechOptions = [
			new SupportedOption( OptionEnum::customOptions() ),
		];

		// The Video Agent model is always first — it is the simplest entry point and requires
		// no avatar or voice selection from the caller.
		$models = [
			new ModelMetadata(
				self::VIDEO_AGENT_MODEL_ID,
				'HeyGen Video Agent',
				$videoCapabilities,
				$videoOptions
			),
			new ModelMetadata(
				self::TTS_MODEL_ID,
				'HeyGen Text to Speech',
				$speechCapabilities,
				$speechOptions
			),
		];

		$avatars = $responseData['data']['avatars'] ?? [];

		foreach ( $avatars as $avatar ) {
			if ( empty( $avatar['avatar_id'] ) || empty( $avatar['avatar_name'] ) ) {
				continue;
			}

			$models[] = new ModelMetadata(
				$avatar['avatar_id'],
				$avatar['avatar_name'],
				$videoCapabilities,
				$videoOptions
			);
		}

		return $models;
	}

	/**
	 * Callback function for sorting models by ID, to be used with `usort()`.
	 *
	 * Ensures `heygen-video-agent` always sorts first, followed by avatar models
	 * in alphabetical order by name.
	 *
	 * @since 1.0.0
	 *
	 * @param ModelMetadata $a First model.
	 * @param ModelMetadata $b Second model.
	 * @return int Comparison result.
	 */
	protected function modelSortCallback( ModelMetadata $a, ModelMetadata $b ): int {
		// Always keep the Video Agent model at the top, then TTS, then avatars.
		if ( $a->getId() === self::VIDEO_AGENT_MODEL_ID ) {
			return -1;
		}
		if ( $b->getId() === self::VIDEO_AGENT_MODEL_ID ) {
			return 1;
		}
		if ( $a->getId() === self::TTS_MODEL_ID ) {
			return -1;
		}
		if ( $b->getId() === self::TTS_MODEL_ID ) {
			return 1;
		}

		// Sort remaining avatars alphabetically by display name.
		return strcmp( $a->getName(), $b->getName() );
	}
}
