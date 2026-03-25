<?php

declare(strict_types=1);

namespace HeyGen\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use HeyGen\Metadata\HeyGenModelMetadataDirectory;
use HeyGen\Models\HeyGenSpeechGenerationModel;
use HeyGen\Models\HeyGenVideoGenerationModel;

/**
 * Class for the HeyGen provider.
 *
 * @since 1.0.0
 */
class HeyGen extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return 'https://api.heygen.com';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isVideoGeneration() ) {
				return new HeyGenVideoGenerationModel( $modelMetadata, $providerMetadata );
			}
			if ( $capability->isSpeechGeneration() ) {
				return new HeyGenSpeechGenerationModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$providerMetadataArgs = [
			'heygen',
			'HeyGen',
			ProviderTypeEnum::cloud(),
			'https://app.heygen.com/settings?nav=API',
			RequestAuthenticationMethod::apiKey(),
		];

		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			if ( function_exists( '__' ) ) {
				$providerMetadataArgs[] = __( 'AI video generation with HeyGen avatars.', 'ai-provider-for-hey-gen' );
			} else {
				$providerMetadataArgs[] = 'AI video generation with HeyGen avatars.';
			}
		}

		return new ProviderMetadata( ...$providerMetadataArgs );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Check valid API access by attempting to list models (avatars).
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new HeyGenModelMetadataDirectory();
	}
}
