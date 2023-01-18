<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\User;
use Conduction\CommonGroundBundle\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * HHandles all the user centerd busnes logics.
 */
class UserService
{
    private EntityManagerInterface $entityManager;
    private AuthenticationService $authenticationService;

    /**
     * @param EntityManagerInterface $entityManager
     * @param AuthenticationService  $authenticationService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        AuthenticationService $authenticationService
    ) {
        $this->entityManager = $entityManager;
        $this->authenticationService = $authenticationService;
    }

    /**
     * @param User   $user
     * @param string $password
     *
     * @return bool
     */
    public function validatePassword(User $user, string $password): bool
    {

        // Todo: this oforuce is hacky as hell and a security danger
        return true;
    }

    /**
     * Generates a JWT token for a user.
     *
     * @todo should be moved to authentication service
     *
     * @param array $payload
     *
     * @return string
     */
    public function createJWTToken(array $payload): string
    {
        $algorithmManager = new AlgorithmManager([new RS512()]);
        $pem = $this->fileService->writeFile('privatekey', base64_decode($this->parameterBag->get('private_key')));
        $jwk = JWKFactory::createFromKeyFile($pem);
        $this->fileService->removeFile($pem);

        $jwsBuilder = new JWSBuilder($algorithmManager);
        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();

        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);
    }

    /**
     * Creates a CsrfToken for a user.
     *
     * @todo should be moved to authentication service
     *
     * @param User $user
     *
     * @return CsrfToken
     */
    public function createCsrfToken(User $user): CsrfToken
    {
        $tokenManager = new CsrfTokenManager();

        return $tokenManager->getToken($user->getId()->toString());
    }

    /**
     * Creates the user object responce for login and /me requests.
     *
     * @param User $user
     *
     * @return array
     */
    public function createResponce(User $user)
    {
        // prepare the security groups
        $groups = [];
        $applications = [];

        foreach ($user->getSecurityGroups() as $securityGroup) {
            $securityGroups[] = ['id'=>$securityGroup->getId()->toString(), 'name'=>$securityGroup->getName()];
        }

        foreach ($user->getApplications() as $application) {
            $applications[] = ['id'=>$application->getId()->toString(), 'name'=>$application->getName()];
        }

        // prepare the responce
        $responce = [
            '_self'=> [
                'id'=> $user->getId()->toString(),
            ],
            'id'          => $user->getId()->toString(),
            'organisation'=> [
                'id'  => $user->getOrganisation()->getId()->toString(),
                'name'=> $user->getOrganisation()->getName(),
            ],
            'applications'=> $user->getApplications()->getId()->toString(),
            'username'    => $user->getUsername(),
            'email'       => $user->getEmail(),
            'locale'      => $user->getLocale(),
            'person'      => $user->getPerson(),
            'roles'       => $user->getRoles(),
            'groups'      => $securityGroups,
            'jwtToken'    => $this->createJwtToken($user),
            'csrfToken'   => $this->createCsrfToken($user),
            'organisation'=> $user->getOrganisation()->getId()->toString(),
            'application' => $user->getApplications()->getId()->toString(),
        ];

        return $responce;
    }

    public function validateToken()
    {
    }
}
