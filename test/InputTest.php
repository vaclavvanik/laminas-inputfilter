<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter\Input;
use Laminas\Validator;
use PHPUnit_Framework_TestCase as TestCase;

class InputTest extends TestCase
{
    public function testConstructorRequiresAName()
    {
        $input = new Input('foo');
        $this->assertEquals('foo', $input->getName());
    }

    public function testInputHasEmptyFilterChainByDefault()
    {
        $input = new Input('foo');
        $filters = $input->getFilterChain();
        $this->assertInstanceOf('Laminas\Filter\FilterChain', $filters);
        $this->assertEquals(0, count($filters));
    }

    public function testInputHasEmptyValidatorChainByDefault()
    {
        $input = new Input('foo');
        $validators = $input->getValidatorChain();
        $this->assertInstanceOf('Laminas\Validator\ValidatorChain', $validators);
        $this->assertEquals(0, count($validators));
    }

    public function testCanInjectFilterChain()
    {
        $input = new Input('foo');
        $chain = new Filter\FilterChain();
        $input->setFilterChain($chain);
        $this->assertSame($chain, $input->getFilterChain());
    }

    public function testCanInjectValidatorChain()
    {
        $input = new Input('foo');
        $chain = new Validator\ValidatorChain();
        $input->setValidatorChain($chain);
        $this->assertSame($chain, $input->getValidatorChain());
    }

    public function testInputIsMarkedAsRequiredByDefault()
    {
        $input = new Input('foo');
        $this->assertTrue($input->isRequired());
    }

    public function testRequiredFlagIsMutable()
    {
        $input = new Input('foo');
        $input->setRequired(false);
        $this->assertFalse($input->isRequired());
    }

    public function testInputDoesNotAllowEmptyValuesByDefault()
    {
        $input = new Input('foo');
        $this->assertFalse($input->allowEmpty());
    }

    public function testAllowEmptyFlagIsMutable()
    {
        $input = new Input('foo');
        $input->setAllowEmpty(true);
        $this->assertTrue($input->allowEmpty());
    }

    public function testValueIsNullByDefault()
    {
        $input = new Input('foo');
        $this->assertNull($input->getValue());
    }

    public function testValueMayBeInjected()
    {
        $input = new Input('foo');
        $input->setValue('bar');
        $this->assertEquals('bar', $input->getValue());
    }

    public function testRetrievingValueFiltersTheValue()
    {
        $input  = new Input('foo');
        $input->setValue('bar');
        $filter = new Filter\StringToUpper();
        $input->getFilterChain()->attach($filter);
        $this->assertEquals('BAR', $input->getValue());
    }

    public function testCanRetrieveRawValue()
    {
        $input  = new Input('foo');
        $input->setValue('bar');
        $filter = new Filter\StringToUpper();
        $input->getFilterChain()->attach($filter);
        $this->assertEquals('bar', $input->getRawValue());
    }

    public function testIsValidReturnsFalseIfValidationChainFails()
    {
        $input  = new Input('foo');
        $input->setValue('bar');
        $validator = new Validator\Digits();
        $input->getValidatorChain()->addValidator($validator);
        $this->assertFalse($input->isValid());
    }

    public function testIsValidReturnsTrueIfValidationChainSucceeds()
    {
        $input  = new Input('foo');
        $input->setValue('123');
        $validator = new Validator\Digits();
        $input->getValidatorChain()->addValidator($validator);
        $this->assertTrue($input->isValid());
    }

    public function testValidationOperatesOnFilteredValue()
    {
        $input  = new Input('foo');
        $input->setValue(' 123 ');
        $filter = new Filter\StringTrim();
        $input->getFilterChain()->attach($filter);
        $validator = new Validator\Digits();
        $input->getValidatorChain()->addValidator($validator);
        $this->assertTrue($input->isValid());
    }

    public function testGetMessagesReturnsValidationMessages()
    {
        $input  = new Input('foo');
        $input->setValue('bar');
        $validator = new Validator\Digits();
        $input->getValidatorChain()->addValidator($validator);
        $this->assertFalse($input->isValid());
        $messages = $input->getMessages();
        $this->assertArrayHasKey(Validator\Digits::NOT_DIGITS, $messages);
    }

    public function testSpecifyingMessagesToInputReturnsThoseOnFailedValidation()
    {
        $input = new Input('foo');
        $input->setValue('bar');
        $validator = new Validator\Digits();
        $input->getValidatorChain()->addValidator($validator);
        $input->setErrorMessage('Please enter only digits');
        $this->assertFalse($input->isValid());
        $messages = $input->getMessages();
        $this->assertArrayNotHasKey(Validator\Digits::NOT_DIGITS, $messages);
        $this->assertContains('Please enter only digits', $messages);
    }

    public function testBreakOnFailureFlagIsOffByDefault()
    {
        $input = new Input('foo');
        $this->assertFalse($input->breakOnFailure());
    }

    public function testBreakOnFailureFlagIsMutable()
    {
        $input = new Input('foo');
        $input->setBreakOnFailure(true);
        $this->assertTrue($input->breakOnFailure());
    }

    public function testNotEmptyValidatorAddedWhenIsValidIsCalled()
    {
        $input = new Input('foo');
        $this->assertTrue($input->isRequired());
        $input->setValue('');
        $validatorChain = $input->getValidatorChain();
        $this->assertEquals(0, count($validatorChain->getValidators()));

        $this->assertFalse($input->isValid());
        $messages = $input->getMessages();
        $this->assertArrayHasKey('isEmpty', $messages);
        $this->assertEquals(1, count($validatorChain->getValidators()));

        // Assert that NotEmpty validator wasn't added again
        $this->assertFalse($input->isValid());
        $this->assertEquals(1, count($validatorChain->getValidators()));
    }

    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists()
    {
        $input = new Input('foo');
        $this->assertTrue($input->isRequired());
        $input->setValue('');

        $notEmptyMock = $this->getMock('Laminas\Validator\NotEmpty', array('isValid'));
        $notEmptyMock->expects($this->exactly(1))
                     ->method('isValid')
                     ->will($this->returnValue(false));

        $validatorChain = $input->getValidatorChain();
        $validatorChain->prependValidator($notEmptyMock);
        $this->assertFalse($input->isValid());

        $validators = $validatorChain->getValidators();
        $this->assertEquals(1, count($validators));
        $this->assertEquals($notEmptyMock, $validators[0]['instance']);
    }

    public function testMerge()
    {
        $input  = new Input('foo');
        $input->setValue(' 123 ');
        $filter = new Filter\StringTrim();
        $input->getFilterChain()->attach($filter);
        $validator = new Validator\Digits();
        $input->getValidatorChain()->addValidator($validator);

        $input2 = new Input('bar');
        $input2->merge($input);
        $validatorChain = $input->getValidatorChain();
        $filterChain    = $input->getFilterChain();

        $this->assertEquals(' 123 ', $input2->getRawValue());
        $this->assertEquals(1, $validatorChain->count());
        $this->assertEquals(1, $filterChain->count());

        $validators = $validatorChain->getValidators();
        $this->assertInstanceOf('Laminas\Validator\Digits', $validators[0]['instance']);

        $filters = $filterChain->getFilters()->toArray();
        $this->assertInstanceOf('Laminas\Filter\StringTrim', $filters[0]);
    }
}
