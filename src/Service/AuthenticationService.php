<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\User;
use DateTime;
use GuzzleHttp\Client;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\InvalidHeaderException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\JWT;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Safe\Exceptions\JsonException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @Author Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class AuthenticationService
{

    private ParameterBagInterface $parameterBag;

    private FileService $fileService;


    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        $this->fileService  = new FileService();

    }
    
    /**
     * TODO: REMOVE ME!!!
     *
     * @return void|null
     */
    private function testRemoveMe()
    {
        return null;
    }


    /**
     * Converts a string RSA key to a JWK via the filesystem.
     *
     * @param string $key The key to load
     *
     * @return JWK The resulting Json Web Key
     */
    public function convertRSAKeyToJWK(string $key): JWK
    {
        $filesystem = new Filesystem();
        $filename   = '/srv/api/var/privatekey'.microtime().getmypid();
        $filesystem->dumpFile($filename, $key);
        $jwk = JWKFactory::createFromKeyFile($filename, null, ['use' => 'sig']);
        $filesystem->remove([$filename]);

        return $jwk;

    }//end convertRSAKeyToJWK()


    /**
     * Converts an RSA private key to a JWK.
     *
     * @TODO: This can be merged with the function above by getting the key from the source earlier
     *
     * @param Source $source
     *
     * @return JWK The resulting Json Web Key
     */
    public function convertRSAtoJWK(Source $source): JWK
    {
        if ($source->getPrivateKey()) {
            $rsa = base64_decode($source->getPrivateKey());
        } else {
            $rsa = base64_decode($this->parameterBag->get('jwt.privateKey'));
        }//end if

        $filename = $this->fileService->writeFile('privateKey', $rsa);
        $jwk      = JWKFactory::createFromKeyFile(
            $filename,
            null,
            ['use' => 'sig']
        );
        $this->fileService->removeFile($filename);

        return $jwk;

    }//end convertRSAtoJWK()


    /**
     * Determines the algorithm for the JWT token to create from the source.
     *
     * @param Source $source The source to determine the algorithm for
     *
     * @return string The algorithm to use
     */
    public function getAlgorithm(Source $source): string
    {
        if ($source->getAuth() == 'jwt-HS256' || $source->getAuth() == 'jwt') {
            return 'HS256';
        } else {
            return 'RS512';
        }

    }//end getAlgorithm()


    /**
     * Gets a JWK for a source based on the algorithm of the source.
     *
     * @param string $algorithm
     * @param Source $source
     *
     * @return JWK The resulting Json Web Key
     */
    public function getJWK(string $algorithm, Source $source): JWK
    {
        if ($algorithm == 'HS256') {
            return new JWK(
                [
                    'kty' => 'oct',
                    'k'   => base64_encode(addslashes($source->getSecret())),
                ]
            );
        } else {
            return $this->convertRSAtoJWK($source);
        }

    }//end getJWK()


    /**
     * Gets an application id for a source.
     *
     * @param Source $source The source to dermine the application id for
     *
     * @return string The application ID to use
     */
    public function getApplicationId(Source $source): string
    {
        if ($source->getJwtId() !== null) {
            return $source->getJwtId();
        }

        return $source->getId();

    }//end getApplicationId()


    /**
     * Creates the JWT payload to identify at an external source.
     *
     * @param Source $source The source to create a payload for
     *
     * @return string The JWT payload to use
     */
    public function getJwtPayload(Source $source): string
    {
        $now      = new DateTime('now');
        $clientId = $this->getApplicationId($source);

        return json_encode(
            [
                'iss'                 => $clientId,
                'iat'                 => $now->getTimestamp(),
                'client_id'           => $clientId,
                'user_id'             => $this->parameterBag->get('app_name'),
                'user_representation' => $this->parameterBag->get('app_name'),
            ]
        );

    }//end getJwtPayload()


    /**
     * Creates a JWT token to identify with on the application.
     *
     * @TODO Merge with getJwtToken by splitting the getting of keys and payloads
     *
     * @param string $key     The private key to create a JWT token with
     * @param array  $payload The payload to create a JWT token with
     *
     * @return string The resulting JWT token
     */
    public function createJwtToken(string $key, array $payload): string
    {
        $algorithmManager = new AlgorithmManager([new RS512()]);
        $jwsBuilder       = new JWSBuilder($algorithmManager);

        $jwk = $this->convertRSAKeytoJWK($key);

        $jws           = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $jwsSerializer = new CompactSerializer();

        return $jwsSerializer->serialize($jws, 0);

    }//end createJwtToken()


    /**
     * Create a JWT token from Component settings.
     *
     * @param Source $source The source to authenticate to
     *
     * @return string The resulting JWT token
     */
    public function getJwtToken(Source $source): string
    {
        $algorithmManager = new AlgorithmManager([new HS256(), new RS512()]);
        $algorithm        = $this->getAlgorithm($source);
        $jwsBuilder       = new JWSBuilder($algorithmManager);

        $jwk      = $this->getJWK($algorithm, $source);
        $clientId = $this->getApplicationId($source);
        $payload  = $this->getJwtPayload($source);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => $algorithm])
            ->build();

        $jwsSerializer = new CompactSerializer();

        return $jwsSerializer->serialize($jws, 0);

    }//end getJwtToken()


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

    }//end getCertificate()


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

    }//end removeFiles()


    /**
     * Sends a post with auth info and certificate(s) to fetch a jwt token.
     *
     * @param Source $source
     *
     * @return string $body['access_token'] JWT token.
     */
    private function getVrijbrpToken(Source $source): string
    {
        $guzzleConfig = array_merge(
            $source->getConfiguration(),
            [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'auth'    => [
                    $source->getUsername(),
                    $source->getPassword(),
                ],
            ]
        );
        $guzzleConfig = array_merge($guzzleConfig, $this->getCertificate($guzzleConfig));

        $client = new Client($guzzleConfig);

        $response = $client->post($source->getLocation().'/oauth/token', ['form_params' => ['grant_type' => 'client_credentials', 'scope' => 'api']]);
        $body     = json_decode($response->getBody()->getContents(), true);
        $this->removeFiles($guzzleConfig);

        return $body['access_token'];

    }//end getVrijbrpToken()


    /**
     * Sends a post with auth info to fetch a jwt token.
     *
     * @param Source $source
     *
     * @return string accessToken which is a JWT token.
     */
    private function getPinkToken(Source $source): string
    {
        $guzzleConfig = $source->getConfiguration();
        $client       = new Client($guzzleConfig);
        $response     = $client->post($source->getLocation().'/v1/auth/token', ['form_params' => ['client_id' => $source->getUsername(), 'client_secret' => $source->getPassword()]]);

        return json_decode($response->getBody()->getContents(), true)['accessToken'];

    }//end getPinkToken()


    /**
     * Sends a post with authentication info to an OAuth Token Endpoint to fetch an authentication token.
     *
     * @param Source $source The source to authenticate for.
     *
     * @throws JsonException Thrown if the result can not be json decoded.
     *
     * @return string The authentication token.
     */
    private function getOauthToken(Source $source): string
    {
        $authenticationConfig = $source->getAuthenticationConfig();

        $credentials = [
            'clientId'     => $source->getUsername(),
            'clientSecret' => $source->getPassword(),
        ];

        if (isset($authenticationConfig['case']) === true && $authenticationConfig['case'] === 'snake_case') {
            $credentials = [
                'client_id'     => $source->getUsername(),
                'client_secret' => $source->getPassword(),
            ];
        }

        if (isset($authenticationConfig['additionalFields']) === true) {
            $credentials = array_merge($credentials, $authenticationConfig['additionalFields']);
        }

        switch ($source->getAuthorizationPassthroughMethod()) {
        case 'query':
            $config['query'] = $credentials;
            break;
        case 'form_params':
            $config['form_params'] = $credentials;
            break;
        case 'json':
        default:
            $config['body'] = \Safe\json_encode($credentials);
            break;
        }//end switch

        $guzzleConfig = $source->getConfiguration();
        $client       = new Client($guzzleConfig);
        $response     = $client->post($authenticationConfig['tokenUrl'], $config);

        $result = \Safe\json_decode($response->getBody()->getContents(), true);

        return $result[$authenticationConfig['tokenField']];

    }//end getOauthToken()


    /**
     * Checks from which type of auth we need to fetch a token from.
     *
     * @param Source $source
     * @param string $authType
     *
     * @return string|null Fetched JWT token.
     */
    public function getTokenFromUrl(Source $source, string $authType): ?string
    {
        switch ($authType) {
        case 'vrijbrp-jwt':
            return $this->getVrijbrpToken($source);
        case 'pink-jwt':
            return $this->getPinkToken($source);
        case 'oauth':
            return $this->getOauthToken($source);
        }//end switch

        return null;

    }//end getTokenFromUrl()


    /**
     * Gets a hmac token.
     */
    public function getHmacToken(array $requestOptions, Source $source): string
    {
        // todo: what if we don't have a body, method or url in $requestOptions?
        switch ($requestOptions['method']) {
        case 'POST':
            $md5  = md5($requestOptions['body'], true);
            $post = base64_encode($md5);
            break;
        case 'GET':
        default:
            // todo: what about a get call?
            $get  = 'not a UTF-8 string';
            $post = base64_encode($get);
            break;
        }

        $websiteKey = $source->getApikey();
        $uri        = strtolower(urlencode($requestOptions['url']));
        $nonce      = 'nonce_'.rand(0000000, 9999999);
        $time       = time();

        $hmac = $websiteKey.$requestOptions['method'].$uri.$time.$nonce.$post;
        $s    = hash_hmac('sha256', $hmac, $source->getSecret(), true);
        $hmac = base64_encode($s);

        return 'hmac '.$websiteKey.':'.$hmac.':'.$nonce.':'.$time;

    }//end getHmacToken()


    /**
     * Gets the authentication values through various checks.
     */
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
            $requestOptions['auth'] = [
                $source->getUsername(),
                $source->getPassword(),
            ];
            break;
        case 'vrijbrp-jwt':
        case 'pink-jwt':
        case 'oauth':
            $requestOptions['headers']['Authorization'] = "Bearer {$this->getTokenFromUrl($source, $source->getAuth())}";
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
        }//end switch

        return $requestOptions;

    }//end getAuthentication()


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

    }//end checkRS512()


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

    }//end checkHS256()


    /**
     * Decides if the provided JWT token is signed with the HS256 Algorithm.
     *
     * @param JWT $token The token provided by the user
     *
     * @return bool Whether the token is in HS256 or not
     */
    public function checkRS256(JWT $token)
    {
        $headerChecker = new HeaderCheckerManager([new AlgorithmChecker(['RS256'])], [new JWSTokenSupport()]);

        try {
            $headerChecker->check($token, 0);

            return true;
        } catch (InvalidHeaderException $exception) {
            return false;
        }

    }//end checkRS256()


    /**
     * Checks the algorithm of the JWT token and decides how to generate a JWK from the provided public key.
     *
     * @param JWT    $token     The JWT token sent by the user
     * @param string $publicKey The public key provided by the application
     *
     * @return JWK The resulting JWK for verifying the JWT
     */
    public function checkHeadersAndGetJWK(JWT $token, string $publicKey): JWKSet
    {
        $headerChecker = new HeaderCheckerManager([new AlgorithmChecker(['HS256', 'RS512', 'RS256'])], [new JWSTokenSupport()]);

        try {
            $headerChecker->check($token, 0);
        } catch (InvalidHeaderException $exception) {
            throw $exception;
        }//end try

        if ($this->checkRS512($token)) {
            $publicKeyFile = $this->fileService->writeFile('publickey', $publicKey);
            $jwk           = new JWKSet([JWKFactory::createFromKeyFile($publicKeyFile, null, [])]);
            $this->fileService->removeFile($publicKeyFile);

            return $jwk;
        } else if ($this->checkHS256($token)) {
            return new JWKSet([JWKFactory::createFromSecret($publicKey, ['alg' => 'HS256', 'use' => 'sig'])]);
        } else if ($this->checkRS256($token)) {
            return JWKFactory::createFromJsonObject($publicKey);
        }

    }//end checkHeadersAndGetJWK()


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
        $algorithmManager  = new AlgorithmManager([new HS256(), new RS512(), new RS256()]);
        $jwsVerifier       = new JWSVerifier($algorithmManager);
        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);

        $jws = $serializerManager->unserialize($token);
        $jwk = $this->checkHeadersAndGetJWK($jws, $publicKey);

        if ($jwsVerifier->verifyWithKeySet($jws, $jwk, 0)) {
            return json_decode($jws->getPayload(), true);
        } else {
            throw new AuthenticationException('Unauthorized: The provided Authorization header is invalid', 401);
        }

    }//end verifyJWTToken()


    /**
     * Serializes a user to be used by the token authenticator.
     *
     * @param User             $user    The user to be serialized
     * @param SessionInterface $session The session to use
     *
     * @return array The serialized user
     */
    public function serializeUser(User $user, SessionInterface $session): array
    {
        $time   = new \DateTime();
        $expiry = new \DateTime("+{$this->parameterBag->get('app_session_duration')} seconds");
        $scopes = [];
        foreach ($user->getSecurityGroups() as $securityGroup) {
            $scopes = array_merge($securityGroup->getScopes(), $scopes);
        }//end foreach

        $payload = [
            'userId'       => $user->getId(),
            'username'     => $user->getEmail(),
            'organization' => $user->getOrganisation()->getId()->toString(),
            'locale'       => $user->getLocale(),
            'roles'        => $scopes,
            'session'      => $session->getId(),
            'iss'          => $this->parameterBag->get('app_url'),
            'ias'          => $time->getTimestamp(),
            'exp'          => $expiry->getTimestamp(),
            'person'       => $user->getPerson(),
        ];

        return $payload;

    }//end serializeUser()


}//end class
