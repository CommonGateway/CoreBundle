<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use DateTime;
use GuzzleHttp\Client;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\InvalidHeaderException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWT;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthenticationService
{
    private ParameterBagInterface $parameterBag;
    private FileService $fileService;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        $this->fileService = new FileService();
    }

    /**
     * @param array $component
     *
     * @return JWK
     */
    public function convertRSAtoJWK(Source $source): JWK
    {
        if ($source->getPrivateKey()) {
            $rsa = base64_decode($source->getPrivateKey());
        } else {
            $rsa = base64_decode($this->parameterBag->get('jwt.privateKey'));
        }
        $filename = $this->fileService->writeFile('privateKey', $rsa);
        $jwk = JWKFactory::createFromKeyFile(
            $filename,
            null,
            [
                'use' => 'sig',
            ]
        );
        $this->fileService->removeFile($filename);
    }

    public function getAlgorithm(Source $source): string
    {
        if ($source->getAuth() == 'jwt-HS256' || $source->getAuth() == 'jwt') {
            return 'HS256';
        } else {
            return 'RS512';
        }
    }

    public function getJWK(string $algorithm, Source $source): JWK
    {
        if ($algorithm == 'HS256') {
            return new JWK([
                'kty' => 'oct',
                'k'   => base64_encode(addslashes($source->getSecret())),
            ]);
        } else {
            return $this->convertRSAtoJWK($source);
        }
    }

    public function getApplicationId(Source $source): string
    {
        if ($source->getJwtId()) {
            return $source->getJwtId();
        } else {
            return $source->getId();
        }
    }

    public function getJwtPayload(Source $source): string
    {
        $now = new DateTime('now');
        $clientId = $this->getApplicationId($source);

        return json_encode([
            'iss'                 => $clientId,
            'iat'                 => $now->getTimestamp(),
            'client_id'           => $clientId,
            'user_id'             => $this->parameterBag->get('app_name'),
            'user_representation' => $this->parameterBag->get('app_name'),
        ]);
    }

    /**
     * Create a JWT token from Component settings.
     *
     * @param array $component The code of the component
     * @param string The JWT token
     */
    public function getJwtToken(Source $source): string
    {
        $algorithmManager = new AlgorithmManager([new HS256(), new RS512()]);
        $algorithm = $this->getAlgorithm($source);
        $jwsBuilder = new JWSBuilder($algorithmManager);

        $jwk = $this->getJWK($algorithm, $source);
        $clientId = $this->getApplicationId($source);
        $payload = $this->getJwtPayload($source);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => $algorithm])
            ->build();

        $jwsSerializer = new CompactSerializer();

        return $jwsSerializer->serialize($jws, 0);
    }

    /**
     * Writes the certificate and ssl keys to disk, returns the filenames.
     *
     * @param array $config The configuration as stored in the source
     *
     * @return array The overrides on the configuration with filenames instead of certificate contents
     */
    public function getCertificate(array $config): array
    {
        $configs = [];
        if (isset($config['cert'])) {
            $configs['cert'] = $this->fileService->writeFile('certificate', $config['cert']);
        }
        if (isset($config['ssl_key'])) {
            $configs['ssl_key'] = $this->fileService->writeFile('privateKey', $config['ssl_key']);
        }
        if (isset($config['verify']) && is_string($config['verify'])) {
            $configs['verify'] = $this->fileService->writeFile('verify', $config['ssl_key']);
        }

        return $configs;
    }

    /**
     * Removes certificates and private keys from disk if they are not necessary anymore.
     *
     * @param array $config The configuration with filenames
     *
     * @return void
     */
    public function removeFiles(array $config): void
    {
        if (isset($config['cert'])) {
            $this->fileService->removeFile($config['cert']);
        }
        if (isset($config['ssl_key'])) {
            $this->fileService->removeFile($config['ssl_key']);
        }
        if (isset($config['verify']) && is_string($config['verify'])) {
            $this->fileService->removeFile($config['verify']);
        }
    }

    public function getTokenFromUrl(Source $source): string
    {
        $guzzleConfig = array_merge($source->getConfiguration(), [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'auth'    => [$source->getUsername(), $source->getPassword()],
        ]);
        $guzzleConfig = array_merge($guzzleConfig, $this->getCertificate($guzzleConfig));

        $client = new Client($guzzleConfig);

        $response = $client->post($source->getLocation().'/oauth/token', ['form_params' => ['grant_type' => 'client_credentials', 'scope' => 'api']]);
        $body = json_decode($response->getBody()->getContents(), true);
        $this->removeFiles($guzzleConfig);

        return $body['access_token'];
    }

    public function getHmacToken(array $requestOptions, Source $source): string
    {
        // todo: what if we don't have a body, method or url in $requestOptions?
        switch ($requestOptions['method']) {
            case 'POST':
                $md5 = md5($requestOptions['body'], true);
                $post = base64_encode($md5);
                break;
            case 'GET':
            default:
                // todo: what about a get call?
                $get = 'not a UTF-8 string';
                $post = base64_encode($get);
                break;
        }

        $websiteKey = $source->getApikey();
        $uri = strtolower(urlencode($requestOptions['url']));
        $nonce = 'nonce_'.rand(0000000, 9999999);
        $time = time();

        $hmac = $websiteKey.$requestOptions['method'].$uri.$time.$nonce.$post;
        $s = hash_hmac('sha256', $hmac, $source->getSecret(), true);
        $hmac = base64_encode($s);

        return 'hmac '.$websiteKey.':'.$hmac.':'.$nonce.':'.$time;
    }

    public function getAuthentication(Source $source): array
    {
        $requestOptions = [];
        switch ($source->getAuth()) {
            case 'jwt-HS256':
            case 'jwt-RS512':
            case 'jwt':
                $requestOptions['headers']['Authorization'] = 'Bearer '.$this->getJwtToken($source);
                break;
            case 'username-password':
                $requestOptions['auth'] = [$source->getUsername(), $source->getPassword()];
                break;
            case 'vrijbrp-jwt':
                $requestOptions['headers']['Authorization'] = "Bearer {$this->getTokenFromUrl($source)}";
                break;
            case 'hmac':
                $requestOptions['headers']['Authorization'] = $this->getHmacToken($requestOptions, $source);
                break;
            case 'apikey':
                if ($source->getAuthorizationHeader()) {
                    switch ($source->getAuthorizationPassthroughMethod()) {
                        case 'query':
                            $requestOptions['query'][$source->getAuthorizationHeader()] = $source->getApiKey();
                            break;
                        default:
                            $requestOptions['headers'][$source->getAuthorizationHeader()] = $source->getApiKey();
                            break;
                    }
                } else {
                    $requestOptions['headers']['Authorization'] = $source->getApiKey();
                }
                break;
            default:
                break;
        }

        return $requestOptions;
    }

    /**
     * Decides if the provided JWT token is signed with the RS512 Algorithm.
     *
     * @param JWT $token The token provided by the user
     *
     * @return bool Whether the token is in HS256 or not
     */
    public function checkRS512(JWT $token)
    {
        $headerChecker = new HeaderCheckerManager([new AlgorithmChecker(['RS512'])], [new JWSTokenSupport()]);

        try {
            $headerChecker->check($token, 0);

            return true;
        } catch (InvalidHeaderException $exception) {
            return false;
        }
    }

    /**
     * Decides if the provided JWT token is signed with the HS256 Algorithm.
     *
     * @param JWT $token The token provided by the user
     *
     * @return bool Whether the token is in HS256 or not
     */
    public function checkHS256(JWT $token)
    {
        $headerChecker = new HeaderCheckerManager([new AlgorithmChecker(['HS256'])], [new JWSTokenSupport()]);

        try {
            $headerChecker->check($token, 0);

            return true;
        } catch (InvalidHeaderException $exception) {
            return false;
        }
    }

    /**
     * Checks the algorithm of the JWT token and decides how to generate a JWK from the provided public key.
     *
     * @param JWT    $token     The JWT token sent by the user
     * @param string $publicKey The public key provided by the application
     *
     * @return JWK The resulting JWK for verifying the JWT
     */
    public function checkHeadersAndGetJWK(JWT $token, string $publicKey): JWK
    {
        $headerChecker = new HeaderCheckerManager([new AlgorithmChecker(['HS256', 'RS512'])], [new JWSTokenSupport()]);

        try {
            $headerChecker->check($token, 0);
        } catch (InvalidHeaderException $exception) {
            throw $exception;
        }

        if ($this->checkRS512($token)) {
            $publicKeyFile = $this->fileService->writeFile('publickey', $publicKey);
            $jwk = JWKFactory::createFromKeyFile($publicKeyFile, null, []);
            $this->fileService->removeFile($publicKeyFile);

            return $jwk;
        } elseif ($this->checkHS256($token)) {
            return JWKFactory::createFromSecret($publicKey, ['alg' => 'HS256', 'use' => 'sig']);
        }
    }

    /**
     * Verifies the JWT token and returns the payload if the JWT token is valid.
     *
     * @param string $token     The token to verify
     * @param string $publicKey The public key to verify the token to
     *
     * @throws HttpException Thrown when the token cannot be verified
     *
     * @return array The payload of the token
     */
    public function verifyJWTToken(string $token, string $publicKey): array
    {
        $algorithmManager = new AlgorithmManager([new HS256(), new RS512()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);

        $jws = $serializerManager->unserialize($token);
        $jwk = $this->checkHeadersAndGetJWK($jws, $publicKey);

        if ($jwsVerifier->verifyWithKey($jws, $jwk, 0)) {
            return json_decode($jws->getPayload(), true);
        } else {
            throw new AuthenticationException('Unauthorized: The provided Authorization header is invalid', 401);
        }
    }
}
