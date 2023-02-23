<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Entity;
use App\Entity\Attribute;
use App\Exception\GatewayException;
use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Factory;
use Respect\Validation\Rules;
use Respect\Validation\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service handles the creation of validations for entities.
 *
 * @author Ruben van der Linde (ruben@conduction.nl)
 */
class ValidationService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var LoggerInterfae
     */
    private LoggerInterfae $logger;

    /**
     * @param EntityManagerInterface $entityManager The Entity Manager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        LoggerInterfae $objectLogger
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $objectLogger;
    }//end __construct()

    /**
     * Validates an object
     *
     * @param ObjectEntity $object
     * @param String $method
     * @return void
     */
    public function validateObject(ObjectEntity $object, String $method = 'POST'){

        $validator = $this->getEntityValidator($object->getEntity(), $method);
        $data = $object-toArray();

        return $validator->validate($data);
    }

    /**
     * Create an validation opbject for an entity
     *
     * @param Entity $entity The entity for wich the validation is created
     *
     * @return array
     */
    public function getEntityValidator(Entity $entity, String $method = 'POST'): Validator
    {
        // Lets make sure we have a vallid method.
        if(in_array($method, ['POST','PUT'.'UPDATE','PATCH'] === false)){
            $this->logger->error('Method not allowed for validation',['method'=>$method,'entity'=>$entity->getId()->toString()]);
        }

        // Try and get a validator for this Entity(+method) from cache.
        $item = $this->cache->getItem('entityValidators_'.$entity->getId()->toString().'_'.$method);
        if ($item->isHit()) {
            //return $item->get(); // TODO: put this back so that we use caching, after https://conduction.atlassian.net/browse/GW-183 is fixed.
        }

        $validator = new Validator();

        foreach($entity->getAtributtes() as $attribute){
            $validator = $this->getAttributeValidator($attribute, $method, $validator);
        }

        // Stuf the validator into the cache
        $item->set($validator);
        $item->tag('entityValidator'); // Tag for all Entity Validators
        $item->tag('entityValidator_'.$entity->getId()->toString()); // Tag for the Validators of this specific Entity.

        $this->cache->save($item);

        return $validator;
    }//end getEntityValidator();

    /**
     * @param Attribute $attribute
     * @param Validator|null $attribute
     *
     * @return Validator
     */
    public function getAttributeValidator(Attribute $attribute, String $method = 'POST', ?Validator $validator): Validator
    {
        // Make sure we have a validator.
        if(isset($validator) === false){
            $validator = new Validator();
        }

        // Lets see if the attribute might be tutched.
        if(
            ($this->method != 'POST' && $attribute->getValidation('immutable') === true) ||
            $attribute->getValidation('readOnly') === true
        ){
            // If immutable this attribute should not be present when doing a PUT or PATCH.
            $validator->addRule(new Rules\Not(new Rules\Key($attribute->getName())));
            // Skip any other validations
            return $validator;
        }

        // Check if the atribute is required
        if($attribute->getValidation('required') === true){
            // Todo:
            //$validator->addRule($attribute->getName(),);
        }

        // Validate on type.
        $typeRule = $this->getAttTypeRule($attribute);
        if($typeRule !== false){
            $validator->addRule(
                $attribute->getName(),
                $typeRule
            );
        }

        //Validate on fromat
        $formatRule = $this->getAttFormatRule($attribute);
        if($formatRule !== false){
            $validator->addRule(
                $attribute->getName(),
                $formatRule
            );
        }

        // Lets add the rules
        foreach($attribute->getValidations() as $validation => $validationValue){
            $rule =  $this->getAttributeRule($attribute, $validation, $validationValue);

            // Lets see if it is a valid rule
            if($rule == false){
                continue;
            }

            $validator->addRule(
                $attribute->getName(),
                $rule
            );
        }

        return $validator ;
    }//end getEntityValidator()

    /**
     * Gets a specific rule for an attributte
     *
     * @param Attribute $attribute The atribute
     * @param string $validation The validation
     * @param string|array|bool $validation The validation
     * @return Rule|Bool
     */
    public function getAttributeRule(Attribute $attribute, string $validation, $validationValue): mixed
    {
        $validations = $attribute->getValidations();
        switch ($validation) {
            case 'enum':
                return new Rules\In($validationValue);
            case 'multipleOf':
                return new Rules\Multiple($validationValue);
            case 'maximum':
                return new Rules\Max($validationValue);
            case 'exclusiveMaximum':
                return new Rules\LessThan($validationValue);
            case 'minimum':
                return new Rules\Min($validationValue);
            case 'exclusiveMinimum':
                return new Rules\GreaterThan($validationValue);
            case 'minLength':
            case 'maxLength':
                return new Rules\Length($validations['minLength'] ?? null, $validations['maxLength'] ?? null);
            case 'maxItems':
            case 'minItems':
                return new Rules\Length($validations['minItems'] ?? null, $validations['maxItems'] ?? null);
            case 'maxProperties':
            case 'minProperties':
                return new Rules\Length($validations['minProperties'] ?? null, $validations['maxProperties'] ?? null);
            case 'minDate':
                return new Rules\Min(new DateTime($validationValue));
            case 'maxDate':
                return new Rules\Max(new DateTime($validationValue));
            case 'maxFileSize':
            case 'minFileSize':
                return new Rules\Key(
                    'base64',
                    new CustomRules\Base64Size($validations['minFileSize'] ?? null, $validations['maxFileSize'] ?? null),
                    true
                );
            case 'fileTypes':
                return new Rules\Key(
                    'base64',
                    new CustomRules\Base64MimeTypes($validationValue),
                    true
                );
            default:
                // we should never end up here
                // Todo: error.
                return false;

        }//end switch

    }//end getAttributeRule()

    /**
     * Gets the correct Rule(s) for the type of the given Attribute.
     *
     * @param Attribute $attribute
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Rules\AbstractRule|Bool
     */
    private function getAttTypeRule(Attribute $attribute, int $level): Mixed
    {
        switch ($attribute->getType()) {
            case 'string':
            case 'text':
                return new Rules\StringType();
            case 'integer':
            case 'int':
                return new Rules\IntType();
            case 'float':
                return new Rules\FloatType();
            case 'number':
                return new Rules\Number();
            case 'date':
                return new Rules\OneOf(
                    new Rules\Date('d-m-Y'),
                    new Rules\Date('Y-m-d'),
                );
            case 'datetime':
                // todo: make a custom rule that checks if we can do new DateTime() with the input value to allow multiple formats?
                // default format for Rules\DateTime = 'c' = ISO standard -> Y-m-dTH:i:s+timezone(00:00)
                return new Rules\OneOf(
                    new Rules\DateTime('d-m-Y'),
                    new Rules\DateTime('d-m-Y H:i:s'),
                    new Rules\DateTime('d-m-YTH:i:s'),
                    new Rules\DateTime('Y-m-d'),
                    new Rules\DateTime('Y-m-d H:i:s'),
                    new Rules\DateTime('Y-m-dTH:i:s'),
                    new Rules\DateTime('Y-m-d\TH:i:s'),
                    new Rules\DateTime('Y-m-d\U\T\CH:i:s'),
                );
            case 'array':
                return new Rules\ArrayType();
            case 'boolean':
            case 'bool':
                return new Rules\BoolType();
            case 'file':
                return new CustomRules\Base64File();
            case 'object':
                return $this->getObjectValidator($attribute, $level);
            default:
                // Todo: error.
                return false;
        }//end switch

    }//end getAttTypeRule()

    /**
     * Gets the correct Rule for the format of the given Attribute. If attribute has no format this will return alwaysValid.
     *
     * @param Attribute $attribute
     *
     * @throws GatewayException
     *
     * @return Rules\AbstractRule|bool
     */
    private function getAttFormatRule(Attribute $attribute): Mixed
    {
        $format = $attribute->getFormat();

        // Let be a bit compassionate, considarate and above all compatible
        $format = str_replace(['telephone'], ['phone'], $format);

        switch ($format) {
            case 'countryCode':
                return new Rules\CountryCode();
            case 'bsn':
                return new Rules\Bsn();
            case 'rsin':
                return new CustomRules\Rsin();
            case 'url':
                return new Rules\Url();
            case 'uuid':
                return new Rules\Uuid();
            case 'email':
                return new Rules\Email();
            case 'phone':
                return new Rules\Phone();
            case 'json':
                return new Rules\Json();
            case 'dutch_pc4':
                return new CustomRules\DutchPostalcode();
            case 'date':
                // For now.
            case 'duration':
                // For now.
            case 'uri':
                // For now.
            case 'int64':
                // For now.
            case 'byte':
                // For now.
            case 'urn':
                // For now.
            case 'reverse-dns':
                // For now.
            case 'Y-m-d\TH:i:s':
            case 'Y-m-d':
                // For now.
            case null:
                // If attribute has no format return alwaysValid.
                return new Rules\AlwaysValid();
            default:
                // Todo: error
                return false;
        }//end switch

    }//end getAttFormatRule()

}//end class
