<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Exception\GatewayException;
use CommonGateway\CoreBundle\Service\Validation\Rules as CustomRules;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Factory;
use Respect\Validation\Rules;
use Respect\Validation\Validator;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Author Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ValidationService
{

    /**
     * The Entity Manager.
     *
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * The cache interface.
     *
     * @var CacheInterface
     */
    public CacheInterface $cache;

    /**
     * The method used during a request. Depending on this method we should validate differently.
     *
     * @var string
     */
    private string $method;

    /**
     * The level used to check for max depth when validating sub-objects.
     *
     * @var integer
     */
    private int $level;

    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager The Entity Manager.
     * @param CacheInterface         $cache         The cache interface.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache
    ) {
        $this->entityManager = $entityManager;
        $this->cache         = $cache;

        Factory::setDefaultInstance(
            (new Factory())
                ->withRuleNamespace('CommonGateway\CoreBundle\Service\Validation\Rules')
                ->withExceptionNamespace('CommonGateway\CoreBundle\Service\Validation\Exceptions')
        );

    }//end __construct()

    /**
     * Validates an array with data using the Validator for the given Entity.
     *
     * @param array  $data   The data to validate.
     * @param Entity $entity The entity used for validation.
     * @param string $method used to be able to use different validations for different methods.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return string[]|void
     */
    public function validateData(array $data, Entity $entity, string $method)
    {
        // We could use a different function to set the $method, but this way we can only validate data if we also have a method.
        if (in_array($method, ['POST', 'PUT', 'PATCH']) === false) {
            throw new GatewayException(
                'This validation method is not allowed.',
                null,
                null,
                [
                    'data'         => $method,
                    'path'         => $entity->getName(),
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]
            );
        }

        // This method is used for the immutable and unsetable Rules later in addAttributeValidators().
        $this->method = strtoupper($method);
        $validator    = $this->getEntityValidator($entity);

        // Todo: what if we have fields in $data that do not exist on this Entity?
        try {
            $validator->assert($data);
        } catch (NestedValidationException $exception) {
            return $exception->getMessages();
        }

    }//end validateData()

    /**
     * Gets a Validator for the given Entity, uses caching.
     *
     * @param Entity $entity The entity used for validation.
     * @param int    $level  The level used to check for max depth when validating sub-objects.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return Validator
     */
    private function getEntityValidator(Entity $entity, int $level = 0): Validator
    {
        // Max Depth.
        if ($level >= $entity->getMaxDepth()) {
            // Todo: make it so that if we reach max depth we throw an error if input is provided.
            return new Validator();
        }

        $this->level = $level;

        // Todo: put this back so that we use caching, after https://conduction.atlassian.net/browse/GW-183 is fixed.
        // Try and get a validator for this Entity(+method) from cache.
        // $item = $this->cache->getItem('entityValidators_'.$entity->getId()->toString().'_'.$this->method);
        // if ($item->isHit() === true) {
            // return $item->get();
        // }
        // No Validator found in cache for this Entity(+method), so create a new Validator and cache that.
        $validator = new Validator();
        $validator = $this->addAttributeValidators($entity, $validator);

        // Todo: put this back so that we use caching, after https://conduction.atlassian.net/browse/GW-183 is fixed.
        // $item->set($validator);
        // Tag for all Entity Validators.
        // $item->tag('entityValidator');
        // Tag for the Validators of this specific Entity.
        // $item->tag('entityValidator_'.$entity->getId()->toString());
        // $this->cache->save($item);
        return $validator;

    }//end getEntityValidator()

    /**
     * Adds Attribute Validators to an Entity Validator.
     *
     * @param Entity    $entity    The entity used for validation.
     * @param Validator $validator The entity validator we are going to add attribute validation to.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return Validator
     */
    private function addAttributeValidators(Entity $entity, Validator $validator): Validator
    {
        foreach ($entity->getAttributes() as $attribute) {
            if (($this->method === 'PUT' || $this->method === 'PATCH')
                && isset($attribute->getValidations()['immutable']) === true
                && $attribute->getValidations()['immutable'] === true
            ) {
                // If immutable this attribute should not be present when doing a PUT or PATCH.
                $validator->addRule(new Rules\Not(new Rules\Key($attribute->getName())));
                // Skip any other validations.
                continue;
            }

            if ($this->method === 'POST' && isset($attribute->getValidations()['unsetable']) === true
                && $attribute->getValidations()['unsetable'] === true
            ) {
                // If unsetable this attribute should not be present when doing a POST.
                $validator->addRule(new Rules\Not(new Rules\Key($attribute->getName())));
                // Skip any other validations.
                continue;
            }

            if (isset($attribute->getValidations()['readOnly']) === true && $attribute->getValidations()['readOnly'] === true) {
                // If readOnly this attribute should not be present.
                $validator->addRule(new Rules\Not(new Rules\Key($attribute->getName())));
                // Skip any other validations.
                continue;
            }

            // If we need to check conditional Rules add these Rules in one AllOf Rule, else $conditionals = AlwaysValid Rule.
            $conditionals = $this->getConditionalsRule($attribute);

            // If we need to check conditionals the $conditionals Rule above will do so in this When Rule below.
            $validator->addRule(
                new Rules\When(
                // IF (the $conditionals Rule does not return any exceptions).
                    $conditionals,
                    // TRUE (continue with the required rule, incl inversedBy check).
                    $this->checkIfAttRequired($attribute),
                    // FALSE (return exception message from $conditionals Rule).
                    $conditionals
                )
            );
        }//end foreach

        return $validator;

    }//end addAttributeValidators()

    /**
     * Returns an AllOf Rule with all conditional Rules for the given Attribute.
     *
     * @param Attribute $attribute The attribute we are adding conditional validation Rules for.
     *
     * @throws ComponentException
     *
     * @return Rules\AllOf
     */
    private function getConditionalsRule(Attribute $attribute): Rules\AllOf
    {
        // If (JsonLogic for) requiredIf isn't set.
        $requiredIf = new Rules\AlwaysValid();
        if (isset($attribute->getValidations()['requiredIf']) === true && empty($attribute->getValidations()['requiredIf']) === false) {
            // Todo: this works but doesn't give a nice and clear error response why the rule is broken. ("x must be present").
            $requiredIf = new Rules\When(
            // IF (the requiredIf JsonLogic finds a match / is true).
                new CustomRules\JsonLogic($attribute->getValidations()['requiredIf']),
                // TRUE (attribute is required).
                new Rules\Key($attribute->getName()),
                // FALSE.
                new Rules\AlwaysValid()
            );
        }

        // If JsonLogic for forbiddenIf isn't set.
        $forbiddenIf = new Rules\AlwaysValid();
        if (isset($attribute->getValidations()['forbiddenIf']) === true && empty($attribute->getValidations()['forbiddenIf']) === false) {
            // Todo: this works but doesn't give a nice and clear error response why the rule is broken. ("x must not be present").
            $forbiddenIf = new Rules\When(
            // IF (the requiredIf JsonLogic finds a match / is true).
                new CustomRules\JsonLogic($attribute->getValidations()['forbiddenIf']),
                // TRUE (attribute should not be present).
                new Rules\Not(new Rules\Key($attribute->getName())),
                // FALSE.
                new Rules\AlwaysValid()
            );
        }

        // Todo: this works but doesn't give a nice and clear error response why the rule is broken. ("allOf": broken rules).
        return new Rules\AllOf(
            $requiredIf,
            $forbiddenIf
        );

    }//end getConditionalsRule()

    /**
     * This function helps determine if we might want to skip the required check because of inversedBy.
     *
     * @param Attribute $attribute The attribute we are adding this validation rule for.
     *
     * @return bool Returns always false, unless we might want to skip the required check because of inversedBy.
     */
    private function checkInversedBy(Attribute $attribute): bool
    {
        if ($attribute->getType() === 'object'
            && empty($attribute->getObject()) === false
            && empty($attribute->getInversedBy()) === false
            && $attribute->getInversedBy()->getEntity() === $attribute->getObject()
        ) {
            return true;
        }

        return false;

    }//end checkInversedBy()

    /**
     * Returns a Rule that makes sure an Attribute is present if it is required. Continues with the 'normal' / other Attribute validations after that.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws CacheException|ComponentException|GatewayException|InvalidArgumentException
     *
     * @return Rules\AbstractRule
     */
    private function checkIfAttRequired(Attribute $attribute): Rules\AbstractRule
    {
        // If attribute is required and an 'inversedBy required loop' is possible.
        if (isset($attribute->getValidations()['required']) === true && $attribute->getValidations()['required'] === true && $this->checkInversedBy($attribute) === true && $this->level != 0) {
            // Todo: this is an incomplete solution to the inversedBy required loop problem, because this way fields that are inversedBy are never required unless they are on level 0...
            return new Rules\Key(
                $attribute->getName(),
                $this->getAttributeValidator($attribute),
                // Mandatory = required validation. False = not required.
                false
            );

            // Todo: JsonLogic needs to be able to check parent attributes/entities in the request body for this to work:
            // Make sure we only make this attribute required if it is not getting auto connected because of inversedBy
            // We can do this by checking if the Attribute->getInversedBy attribute is already present in the body.
            // return new Rules\When(
            // IF
            // new CustomRules\JsonLogic(["var" => $attribute->getInversedBy()->getName()]),
            // TRUE
            // new Rules\Key(
            // $attribute->getName(),
            // $this->getAttributeValidator($attribute),
            // Mandatory = required validation. False = not required.
            // false
            // ),
            // FALSE
            // new Rules\Key(
            // $attribute->getName(),
            // $this->getAttributeValidator($attribute),
            // Mandatory = required validation. True = required.
            // true
            // )
            // );
        }//end if

        // Else, continue with the 'normal' required validation.
        return new Rules\Key(
            $attribute->getName(),
            $this->getAttributeValidator($attribute),
            // Mandatory = required validation.
            $this->method !== 'PATCH' && isset($attribute->getValidations()['required']) && $attribute->getValidations()['required'] === true
        );

    }//end checkIfAttRequired()

    /**
     * Gets a Validator for the given Attribute. This function is the point from where we start validating the actual value of an Attribute.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getAttributeValidator(Attribute $attribute): Validator
    {
        $attributeValidator = new Validator();

        return $attributeValidator->addRule($this->checkIfAttNullable($attribute));

    }//end getAttributeValidator()

    /**
     * Checks if the attribute is nullable and adds the correct Rules for this if needed.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Rules\AbstractRule
     */
    private function checkIfAttNullable(Attribute $attribute): Rules\AbstractRule
    {
        // Check if this attribute can be null.
        if (isset($attribute->getValidations()['nullable']) === true && $attribute->getValidations()['nullable'] !== false) {
            // When works like this: When(IF, TRUE, FALSE).
            return new Rules\When(new Rules\NotEmpty(), $this->checkIfAttMultiple($attribute), new Rules\AlwaysValid());
        }

        return $this->checkIfAttMultiple($attribute);

    }//end checkIfAttNullable()

    /**
     * Checks if the attribute is an array (multiple) and adds the correct Rules for this if needed.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function checkIfAttMultiple(Attribute $attribute): Validator
    {
        // Get all validations for validating this Attributes value in one Validator.
        // This includes Rules for the type, format and possible other validations.
        $attRulesValidator = $this->getAttTypeValidator($attribute);

        // Check if this attribute should be an array.
        if (isset($attribute->getValidations()['multiple']) === true && $attribute->getValidations()['multiple'] === true) {
            // Todo: When we get a validation error we somehow need to get the index of that object in the array for in the error data...
            $multipleValidator = new Validator();
            $multipleValidator->addRule(new Rules\Each($attRulesValidator));
            if (isset($attribute->getValidations()['uniqueItems']) === true && $attribute->getValidations()['uniqueItems'] === true) {
                $multipleValidator->addRule(new Rules\Unique());
            }

            return $multipleValidator;
        }

        return $attRulesValidator;

    }//end checkIfAttMultiple()

    /**
     * Gets a Validator for the type of the given Attribute. (And format and other validations if type validation is true).
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getAttTypeValidator(Attribute $attribute): Validator
    {
        $attTypeValidator = new Validator();

        // Get the Rule for the type of this Attribute.
        // (Note: make sure to not call functions like this twice when using the Rule twice in a When Rule).
        $attTypeRule = $this->getAttTypeRule($attribute);

        // Check if the format of the attribute is not null
        if ($attribute->getFormat() !== null) {
            // If attribute type is correct and format is not null, continue validation of attribute format.
            $attTypeValidator->addRule(
                new Rules\When(
                    // IF.
                    $attTypeRule,
                    // TRUE - Validate the format since format is not null.
                    $this->getAttFormatValidator($attribute),
                    // FALSE - Just confirm the type if format is null.
                    $attTypeRule
                )
            );
        } else {
            // If the format is null, only validate the type.
            $attTypeValidator->addRule($attTypeRule);
        }

        return $attTypeValidator;

    }//end getAttTypeValidator()

    /**
     * Gets a Validator for the format of the given Attribute. (And other validations if format validation is true).
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws ComponentException|GatewayException
     *
     * @return Validator
     */
    private function getAttFormatValidator(Attribute $attribute): Validator
    {
        $attFormatValidator = new Validator();

        // Get the Rule for the format of this Attribute.
        // (Note: make sure to not call functions like this twice when using the Rule twice in a When Rule).
        $attFormatRule = $this->getAttFormatRule($attribute);

        // If attribute format is correct continue validation of other validationRules.
        $attFormatValidator->addRule(
            new Rules\When(
            // IF.
                $attFormatRule,
                // TRUE.
                $this->getAttValidationRulesValidator($attribute),
                // FALSE.
                $attFormatRule
            )
        );

        return $attFormatValidator;

    }//end getAttFormatValidator()

    /**
     * Gets the correct Rule(s) for the type of the given Attribute.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Rules\AbstractRule
     */
    private function getAttTypeRule(Attribute $attribute): Rules\AbstractRule
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
            return new Rules\Date();
        case 'datetime':
            return new Rules\DateTime();
        case 'array':
            return new Rules\ArrayType();
        case 'boolean':
        case 'bool':
            return new Rules\BoolType();
        case 'file':
            return new CustomRules\Base64File();
        case 'object':
            return $this->getObjectValidator($attribute);
        default:
            throw new GatewayException(
                'Unknown attribute type.',
                null,
                null,
                [
                    'data'         => $attribute->getType(),
                    'path'         => $attribute->getEntity()->getName().'.'.$attribute->getName(),
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]
            );
        }//end switch

    }//end getAttTypeRule()

    /**
     * Gets a Validator for the object of the given Attribute with type = 'object'.
     *
     * @param Attribute $attribute The level used to check for max depth when validating sub-objects.
     *
     * @throws CacheException|GatewayException|InvalidArgumentException|ComponentException
     *
     * @return Validator
     */
    private function getObjectValidator(Attribute $attribute): Validator
    {
        $objectValidator = new Validator();

        // Make sure we do not allow empty string for an object.
        // (will also invalidate null, but if attribute is nullable and the value is null we never get here and never check this rule).
        $objectValidator->addRule(new Rules\NotEmpty());

        // If the input is a UUID, validate if an ObjectEntity with that UUID and Schema = $attribute->getObject() exists.
        $objectValidator->addRule(
            new Rules\When(
            // IF.
                new Rules\Uuid(),
                // TRUE.
                new CustomRules\ObjectExists($this->entityManager, $attribute->getObject() !== null ? $attribute->getObject()->getId()->toString() : null),
                // FALSE.
                new Rules\AlwaysValid()
            )
        );

        // Todo: Make a custom rule for cascading so we can give custom exception messages back?
        // Todo: maybe check if an object with the given UUID actually exists?
        // Validate for cascading.
        if (isset($attribute->getValidations()['cascade']) === false || $attribute->getValidations()['cascade'] === false) {
            // Uuid.
            $objectValidator->addRule(new Rules\OneOf(new Rules\Uuid(), new Rules\Url()));

            return $objectValidator;
        }

        // Array or Uuid.
        $objectValidator->addRule(
            new Rules\OneOf(
                new Rules\ArrayType(),
                new Rules\Uuid(),
                new Rules\Url()
            )
        );
        // If we are allowed to cascade and the input is an array, validate the input array for the Attribute->object Entity.
        $objectValidator->addRule(
            new Rules\When(
            // IF.
                new Rules\ArrayType(),
                // TRUE.
                $this->getEntityValidator($attribute->getObject(), ($this->level + 1)),
                // FALSE.
                new Rules\AlwaysValid()
            )
        );

        return $objectValidator;

    }//end getObjectValidator()

    /**
     * Gets the correct Rule for the format of the given Attribute. If attribute has no format this will return alwaysValid.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws GatewayException
     *
     * @return Rules\AbstractRule
     */
    private function getAttFormatRule(Attribute $attribute): Rules\AbstractRule
    {
        $format = $attribute->getFormat();

        // Let be a bit compassionate and compatible.
        $format = str_replace(['telephone'], ['phone'], $format);

        switch ($format) {
        case 'countryCode':
            return new Rules\CountryCode();
        case 'bsn':
            return new Rules\Bsn();
        case 'rsin':
            return new CustomRules\Rsin();
        case 'url':
            if ($attribute->getType() === 'object') {
                return new Rules\AlwaysValid();
            }
            return new Rules\Url();
        case 'uuid':
            return new Rules\Uuid();
        case 'email':
            return new Rules\Email();
        case 'phone':
            return new Rules\Phone();
        case 'json':
            return new Rules\ArrayType();
        case 'dutch_pc4':
            return new CustomRules\DutchPostalcode();
        case 'date':
        case 'date-time':
        case 'datetime':
            // For now...
        case 'duration':
            // For now...
        case 'uri':
            // For now...
        case 'int64':
            // For now...
        case 'byte':
            // For now...
        case 'urn':
            // For now...
        case 'reverse-dns':
            // For now...
        case 'Y-m-d\TH:i:s':
        case 'Y-m-d':
            // For now...
        case 'oneOf':
            // For now...
        case 'text':
        case null:
            // If attribute has no format return alwaysValid.
            return new Rules\AlwaysValid();
        default:
            throw new GatewayException(
                "Unknown attribute format $format.",
                null,
                null,
                [
                    'data'         => $format,
                    'path'         => $attribute->getEntity()->getName().'.'.$attribute->getName(),
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]
            );
        }//end switch

    }//end getAttFormatRule()

    /**
     * Gets a Validator with the correct Rules for (almost) all the validations of the given Attribute.
     *
     * @param Attribute $attribute The attribute we are adding validation rules for.
     *
     * @throws ComponentException|GatewayException
     *
     * @return Validator
     */
    private function getAttValidationRulesValidator(Attribute $attribute): Validator
    {
        $validationRulesValidator = new Validator();

        foreach ($attribute->getValidations() as $validation => $config) {
            // If we have no config or validation config === false continue without adding a new Rule.
            // And $ignoredValidations here are not done through this getValidationRule function, but somewhere else!
            $ignoredValidations = [
                'required',
                'nullable',
                'multiple',
                'uniqueItems',
                'requiredIf',
                'forbiddenIf',
                'cascade',
                'immutable',
                'unsetable',
                'defaultValue',
            ];
            // Todo: instead of this^ array we could also add these options to the switch in the getValidationRule function but return the AlwaysValid rule?
            // And $todoValidations here are not done yet anywhere, they still need to be added somewhere!
            $todoValidations = [
                'mustBeUnique',
                'pattern',
            ];
            if (empty($config) === true || in_array($validation, $ignoredValidations) === true || in_array($validation, $todoValidations) === true) {
                continue;
            }

            $validationRulesValidator->AddRule($this->getValidationRule($attribute, $validation, $config));
        }//end foreach

        return $validationRulesValidator;

    }//end getAttValidationRulesValidator()

    /**
     * Gets the correct Rule for a specific validation of the given Attribute.
     *
     * @param Attribute $attribute  The attribute we are adding validation rules for.
     * @param string    $validation A single validation key from attribute->validations.
     * @param mixed     $config     A single validation value from attribute->validations.
     *
     * @throws ComponentException|GatewayException|Exception
     *
     * @return Rules\AbstractRule|null
     */
    private function getValidationRule(Attribute $attribute, $validation, $config): ?Rules\AbstractRule
    {
        $validations = $attribute->getValidations();
        switch ($validation) {
        case 'enum':
            return new Rules\In($config);
        case 'multipleOf':
            return new Rules\Multiple($config);
        case 'maximum':
            return new Rules\Max($config);
        case 'exclusiveMaximum':
            return new Rules\LessThan($validations['maximum']);
        case 'minimum':
            return new Rules\Min($config);
        case 'exclusiveMinimum':
            return new Rules\GreaterThan($validations['minimum']);
        case 'minLength':
        case 'maxLength':
            return new Rules\Length(($validations['minLength'] ?? null), ($validations['maxLength'] ?? null));
        case 'maxItems':
        case 'minItems':
            return new Rules\Length(($validations['minItems'] ?? null), ($validations['maxItems'] ?? null));
        case 'maxProperties':
        case 'minProperties':
            return new Rules\Length(($validations['minProperties'] ?? null), ($validations['maxProperties'] ?? null));
        case 'minDate':
            return new Rules\Min(new DateTime($config));
        case 'maxDate':
            return new Rules\Max(new DateTime($config));
        case 'maxFileSize':
        case 'minFileSize':
            return new Rules\Key(
                'base64',
                new CustomRules\Base64Size(($validations['minFileSize'] ?? null), ($validations['maxFileSize'] ?? null)),
                true
            );
        case 'fileTypes':
            return new Rules\Key(
                'base64',
                new CustomRules\Base64MimeTypes($config),
                true
            );
        // For now.
        case 'oneOf':
            // If attribute has no format return alwaysValid.
            return new Rules\AlwaysValid();
        default:
            // we should never end up here.
            if (is_array($config)) {
                $config = http_build_query($config, '', ', ');
            }
            throw new GatewayException(
                'Unknown validation.',
                null,
                null,
                [
                    'data'         => $validation.' set to '.$config,
                    'path'         => $attribute->getEntity()->getName().'.'.$attribute->getName(),
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]
            );
        }//end switch

    }//end getValidationRule()
}//end class
