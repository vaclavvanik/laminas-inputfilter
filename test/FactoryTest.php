<?php

/**
 * @see       https://github.com/laminas/laminas-inputfilter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-inputfilter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-inputfilter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager;
use Laminas\Validator;
use PHPUnit_Framework_TestCase as TestCase;

class FactoryTest extends TestCase
{
    public function testFactoryComposesFilterChainByDefault()
    {
        $factory = new Factory();
        $this->assertInstanceOf('Laminas\Filter\FilterChain', $factory->getDefaultFilterChain());
    }

    public function testFactoryComposesValidatorChainByDefault()
    {
        $factory = new Factory();
        $this->assertInstanceOf('Laminas\Validator\ValidatorChain', $factory->getDefaultValidatorChain());
    }

    public function testFactoryAllowsInjectingFilterChain()
    {
        $factory     = new Factory();
        $filterChain = new Filter\FilterChain();
        $factory->setDefaultFilterChain($filterChain);
        $this->assertSame($filterChain, $factory->getDefaultFilterChain());
    }

    public function testFactoryAllowsInjectingValidatorChain()
    {
        $factory        = new Factory();
        $validatorChain = new Validator\ValidatorChain();
        $factory->setDefaultValidatorChain($validatorChain);
        $this->assertSame($validatorChain, $factory->getDefaultValidatorChain());
    }

    public function testFactoryUsesComposedFilterChainWhenCreatingNewInputObjects()
    {
        $factory       = new Factory();
        $filterChain   = new Filter\FilterChain();
        $pluginManager = new Filter\FilterPluginManager();
        $filterChain->setPluginManager($pluginManager);
        $factory->setDefaultFilterChain($filterChain);
        $input = $factory->createInput(array(
            'name' => 'foo',
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $inputFilterChain = $input->getFilterChain();
        $this->assertNotSame($filterChain, $inputFilterChain);
        $this->assertSame($pluginManager, $inputFilterChain->getPluginManager());
    }

    public function testFactoryUsesComposedValidatorChainWhenCreatingNewInputObjects()
    {
        $factory          = new Factory();
        $validatorChain   = new Validator\ValidatorChain();
        $validatorPlugins = new Validator\ValidatorPluginManager();
        $validatorChain->setPluginManager($validatorPlugins);
        $factory->setDefaultValidatorChain($validatorChain);
        $input = $factory->createInput(array(
            'name' => 'foo',
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $inputValidatorChain = $input->getValidatorChain();
        $this->assertNotSame($validatorChain, $inputValidatorChain);
        $this->assertSame($validatorPlugins, $inputValidatorChain->getPluginManager());
    }

    public function testFactoryInjectsComposedFilterAndValidatorChainsIntoInputObjectsWhenCreatingNewInputFilterObjects()
    {
        $factory          = new Factory();
        $filterPlugins    = new Filter\FilterPluginManager();
        $validatorPlugins = new Validator\ValidatorPluginManager();
        $filterChain      = new Filter\FilterChain();
        $validatorChain   = new Validator\ValidatorChain();
        $filterChain->setPluginManager($filterPlugins);
        $validatorChain->setPluginManager($validatorPlugins);
        $factory->setDefaultFilterChain($filterChain);
        $factory->setDefaultValidatorChain($validatorChain);

        $inputFilter = $factory->createInputFilter(array(
            'foo' => array(
                'name' => 'foo',
            ),
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputFilterInterface', $inputFilter);
        $this->assertEquals(1, count($inputFilter));
        $input = $inputFilter->get('foo');
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $inputFilterChain    = $input->getFilterChain();
        $inputValidatorChain = $input->getValidatorChain();
        $this->assertSame($filterPlugins, $inputFilterChain->getPluginManager());
        $this->assertSame($validatorPlugins, $inputValidatorChain->getPluginManager());
    }

    public function testFactoryWillCreateInputWithSuggestedFilters()
    {
        $factory      = new Factory();
        $htmlEntities = new Filter\HtmlEntities();
        $input = $factory->createInput(array(
            'name'    => 'foo',
            'filters' => array(
                array(
                    'name' => 'string_trim',
                ),
                $htmlEntities,
                array(
                    'name' => 'string_to_lower',
                    'options' => array(
                        'encoding' => 'ISO-8859-1',
                    ),
                ),
            ),
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertEquals('foo', $input->getName());
        $chain = $input->getFilterChain();
        $index = 0;
        foreach ($chain as $filter) {
            switch ($index) {
                case 0:
                    $this->assertInstanceOf('Laminas\Filter\StringTrim', $filter);
                    break;
                case 1:
                    $this->assertSame($htmlEntities, $filter);
                    break;
                case 2:
                    $this->assertInstanceOf('Laminas\Filter\StringToLower', $filter);
                    $this->assertEquals('ISO-8859-1', $filter->getEncoding());
                    break;
                default:
                    $this->fail('Found more filters than expected');
            }
            $index++;
        }
    }

    public function testFactoryWillCreateInputWithSuggestedValidators()
    {
        $factory = new Factory();
        $digits  = new Validator\Digits();
        $input = $factory->createInput(array(
            'name'       => 'foo',
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                $digits,
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 3,
                        'max' => 5,
                    ),
                ),
            ),
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertEquals('foo', $input->getName());
        $chain = $input->getValidatorChain();
        $index = 0;
        foreach ($chain as $validator) {
            switch ($index) {
                case 0:
                    $this->assertInstanceOf('Laminas\Validator\NotEmpty', $validator);
                    break;
                case 1:
                    $this->assertSame($digits, $validator);
                    break;
                case 2:
                    $this->assertInstanceOf('Laminas\Validator\StringLength', $validator);
                    $this->assertEquals(3, $validator->getMin());
                    $this->assertEquals(5, $validator->getMax());
                    break;
                default:
                    $this->fail('Found more validators than expected');
            }
            $index++;
        }
    }

    public function testFactoryWillCreateInputWithSuggestedRequiredFlagAndImpliesAllowEmptyFlag()
    {
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'     => 'foo',
            'required' => false,
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertFalse($input->isRequired());
        $this->assertTrue($input->allowEmpty());
    }

    public function testFactoryWillCreateInputWithSuggestedRequiredFlagAndAlternativeAllowEmptyFlag()
    {
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'     => 'foo',
            'required' => false,
            'allow_empty' => false,
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertFalse($input->isRequired());
        $this->assertFalse($input->allowEmpty());

    }

    public function testFactoryWillCreateInputWithSuggestedAllowEmptyFlagAndImpliesRequiredFlag()
    {
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'        => 'foo',
            'allow_empty' => true,
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertTrue($input->allowEmpty());
        $this->assertFalse($input->isRequired());
    }

    public function testFactoryWillCreateInputWithSuggestedName()
    {
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'        => 'foo',
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertEquals('foo', $input->getName());
    }

    public function testFactoryWillCreateInputWithContinueIfEmptyFlag()
    {
        $factory = new Factory();
        $input = $factory->createInput(array(
            'name'              => 'foo',
            'continue_if_empty' => true,
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $this->assertTrue($input->continueIfEmpty());
    }

    public function testFactoryAcceptsInputInterface()
    {
        $factory = new Factory();
        $input = new Input();

        $inputFilter = $factory->createInputFilter(array(
            'foo' => $input
        ));

        $this->assertInstanceOf('Laminas\InputFilter\InputFilterInterface', $inputFilter);
        $this->assertTrue($inputFilter->has('foo'));
        $this->assertTrue($inputFilter->get('foo') === $input);
    }

    public function testFactoryAcceptsInputFilterInterface()
    {
        $factory = new Factory();
        $input = new InputFilter();

        $inputFilter = $factory->createInputFilter(array(
            'foo' => $input
        ));

        $this->assertInstanceOf('Laminas\InputFilter\InputFilterInterface', $inputFilter);
        $this->assertTrue($inputFilter->has('foo'));
        $this->assertTrue($inputFilter->get('foo') === $input);
    }

    public function testFactoryWillCreateInputFilterAndAllInputObjectsFromGivenConfiguration()
    {
        $factory     = new Factory();
        $inputFilter = $factory->createInputFilter(array(
            'foo' => array(
                'name'       => 'foo',
                'required'   => false,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 3,
                            'max' => 5,
                        ),
                    ),
                ),
            ),
            'bar' => array(
                'allow_empty' => true,
                'filters'     => array(
                    array(
                        'name' => 'string_trim',
                    ),
                    array(
                        'name' => 'string_to_lower',
                        'options' => array(
                            'encoding' => 'ISO-8859-1',
                        ),
                    ),
                ),
            ),
            'baz' => array(
                'type'   => 'Laminas\InputFilter\InputFilter',
                'foo' => array(
                    'name'       => 'foo',
                    'required'   => false,
                    'validators' => array(
                        array(
                            'name' => 'not_empty',
                        ),
                        array(
                            'name' => 'string_length',
                            'options' => array(
                                'min' => 3,
                                'max' => 5,
                            ),
                        ),
                    ),
                ),
                'bar' => array(
                    'allow_empty' => true,
                    'filters'     => array(
                        array(
                            'name' => 'string_trim',
                        ),
                        array(
                            'name' => 'string_to_lower',
                            'options' => array(
                                'encoding' => 'ISO-8859-1',
                            ),
                        ),
                    ),
                ),
            ),
            'bat' => array(
                'type' => 'LaminasTest\InputFilter\TestAsset\CustomInput',
                'name' => 'bat',
            ),
            'zomg' => array(
                'name' => 'zomg',
                'continue_if_empty' => true,
            ),
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputFilter', $inputFilter);
        $this->assertEquals(5, count($inputFilter));

        foreach (array('foo', 'bar', 'baz', 'bat', 'zomg') as $name) {
            $input = $inputFilter->get($name);

            switch ($name) {
                case 'foo':
                    $this->assertInstanceOf('Laminas\InputFilter\Input', $input);
                    $this->assertFalse($input->isRequired());
                    $this->assertEquals(2, count($input->getValidatorChain()));
                    break;
                case 'bar':
                    $this->assertInstanceOf('Laminas\InputFilter\Input', $input);
                    $this->assertTrue($input->allowEmpty());
                    $this->assertEquals(2, count($input->getFilterChain()));
                    break;
                case 'baz':
                    $this->assertInstanceOf('Laminas\InputFilter\InputFilter', $input);
                    $this->assertEquals(2, count($input));
                    $foo = $input->get('foo');
                    $this->assertInstanceOf('Laminas\InputFilter\Input', $foo);
                    $this->assertFalse($foo->isRequired());
                    $this->assertEquals(2, count($foo->getValidatorChain()));
                    $bar = $input->get('bar');
                    $this->assertInstanceOf('Laminas\InputFilter\Input', $bar);
                    $this->assertTrue($bar->allowEmpty());
                    $this->assertEquals(2, count($bar->getFilterChain()));
                    break;
                case 'bat':
                    $this->assertInstanceOf('LaminasTest\InputFilter\TestAsset\CustomInput', $input);
                    $this->assertEquals('bat', $input->getName());
                    break;
                case 'zomg':
                    $this->assertInstanceOf('Laminas\InputFilter\Input', $input);
                    $this->assertTrue($input->continueIfEmpty());
            }
        }
    }

    public function testFactoryWillCreateInputFilterMatchingInputNameWhenNotSpecified()
    {
        $factory     = new Factory();
        $inputFilter = $factory->createInputFilter(array(
            array('name' => 'foo')
        ));

        $this->assertTrue($inputFilter->has('foo'));
        $this->assertInstanceOf('Laminas\InputFilter\Input', $inputFilter->get('foo'));
    }

    public function testFactoryAllowsPassingValidatorChainsInInputSpec()
    {
        $factory = new Factory();
        $chain   = new Validator\ValidatorChain();
        $input   = $factory->createInput(array(
            'name'       => 'foo',
            'validators' => $chain,
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $test = $input->getValidatorChain();
        $this->assertSame($chain, $test);
    }

    public function testFactoryAllowsPassingFilterChainsInInputSpec()
    {
        $factory = new Factory();
        $chain   = new Filter\FilterChain();
        $input   = $factory->createInput(array(
            'name'    => 'foo',
            'filters' => $chain,
        ));
        $this->assertInstanceOf('Laminas\InputFilter\InputInterface', $input);
        $test = $input->getFilterChain();
        $this->assertSame($chain, $test);
    }

    public function testFactoryAcceptsCollectionInputFilter()
    {
        $factory = new Factory();

        $inputFilter = $factory->createInputFilter(array(
            'type'        => 'Laminas\InputFilter\CollectionInputFilter',
            'inputfilter' => new InputFilter(),
            'count'       => 3
        ));

        $this->assertInstanceOf('Laminas\InputFilter\CollectionInputFilter', $inputFilter);
        $this->assertInstanceOf('Laminas\InputFilter\InputFilter', $inputFilter->getInputFilter());
        $this->assertEquals(3, $inputFilter->getCount());
    }

    public function testFactoryWillCreateInputWithErrorMessage()
    {
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'          => 'foo',
            'error_message' => 'My custom error message',
        ));
        $this->assertEquals('My custom error message', $input->getErrorMessage());
    }

    public function testFactoryWillNotGetPrioritySetting()
    {
        //Reminder: Priority at which to enqueue filter; defaults to 1000 (higher executes earlier)
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'    => 'foo',
            'filters' => array(
                array(
                    'name'      => 'string_trim',
                    'priority'  => \Laminas\Filter\FilterChain::DEFAULT_PRIORITY - 1 // 999
                ),
                array(
                    'name'      => 'string_to_upper',
                    'priority'  => \Laminas\Filter\FilterChain::DEFAULT_PRIORITY + 1 //1001
                ),
                array(
                    'name'      => 'string_to_lower', // default priority 1000
                )
            )
        ));

        // We should have 3 filters
        $this->assertEquals(3, $input->getFilterChain()->count());

        // Filters should pop in the following order:
        // string_to_upper (1001), string_to_lower (1000), string_trim (999)
        $index = 0;
        foreach($input->getFilterChain()->getFilters() as $filter) {
            switch ($index) {
                case 0:
                    $this->assertInstanceOf('Laminas\Filter\StringToUpper', $filter);
                    break;
                case 1:
                    $this->assertInstanceOf('Laminas\Filter\StringToLower', $filter);
                    break;
                case 2:
                    $this->assertInstanceOf('Laminas\Filter\StringTrim', $filter);
                    break;
            }
            $index++;
        }
    }

    public function testConflictNameWithInputFilterType()
    {
        $factory = new Factory();

        $inputFilter = $factory->createInputFilter(
            array(
                'type' => array(
                    'required' => true
                )
            )
        );

        $this->assertInstanceOf('Laminas\InputFilter\InputFilter', $inputFilter);
        $this->assertTrue($inputFilter->has('type'));
    }

    public function testCustomFactoryInCollection()
    {
        $factory = new TestAsset\CustomFactory();
        $inputFilter = $factory->createInputFilter(array(
            'type'        => 'collection',
            'input_filter' => new InputFilter(),
        ));
        $this->assertInstanceOf('LaminasTest\InputFilter\TestAsset\CustomFactory', $inputFilter->getFactory());
    }

    /**
     * @group 4838
     */
    public function testCanSetInputErrorMessage()
    {
        $factory = new Factory();
        $input   = $factory->createInput(array(
            'name'          => 'test',
            'type'          => 'Laminas\InputFilter\Input',
            'error_message' => 'Custom error message',
        ));
        $this->assertEquals('Custom error message', $input->getErrorMessage());
    }

    public function testSetInputFilterManagerWithServiceManager()
    {
        $inputFilterManager = new InputFilterPluginManager;
        $serviceManager = new ServiceManager\ServiceManager;
        $serviceManager->setService('ValidatorManager', new Validator\ValidatorPluginManager);
        $serviceManager->setService('FilterManager', new Filter\FilterPluginManager);
        $inputFilterManager->setServiceLocator($serviceManager);
        $factory = new Factory();
        $factory->setInputFilterManager($inputFilterManager);
        $this->assertInstanceOf(
            'Laminas\Validator\ValidatorPluginManager',
            $factory->getDefaultValidatorChain()->getPluginManager()
        );
        $this->assertInstanceOf(
            'Laminas\Filter\FilterPluginManager',
            $factory->getDefaultFilterChain()->getPluginManager()
        );
    }

    public function testSetInputFilterManagerWithoutServiceManager()
    {
        $inputFilterManager = new InputFilterPluginManager();
        $factory = new Factory();
        $factory->setInputFilterManager($inputFilterManager);
        $this->assertSame($inputFilterManager, $factory->getInputFilterManager());
    }

    /**
     * @group 5691
     *
     * @covers \Laminas\InputFilter\Factory::createInput
     */
    public function testSetsBreakChainOnFailure()
    {
        $factory = new Factory();

        $this->assertTrue($factory->createInput(array('break_on_failure' => true))->breakOnFailure());

        $this->assertFalse($factory->createInput(array('break_on_failure' => false))->breakOnFailure());
    }
}
