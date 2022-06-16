<?php

namespace Drupal\Tests\business_rules\Kernel;

use Drupal\business_rules\Events\BusinessRulesEvent;
use Drupal\business_rules\Plugin\BusinessRulesActionManager;
use Drupal\business_rules\Plugin\BusinessRulesConditionManager;
use Drupal\business_rules\Plugin\BusinessRulesReactsOnManager;
use Drupal\business_rules\Plugin\BusinessRulesVariableManager;
use Drupal\business_rules\Util\BusinessRulesProcessor;
use Drupal\business_rules\Util\BusinessRulesUtil;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the BusinessRulesProcessor.
 *
 * @group business_rules
 */
class BusinessRulesProcessorTest extends KernelTestBase {

  /**
   * A mostly-empty service container, which BusinessRulesProcessor requires.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $sutContainer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Set up the service container.
    $this->sutContainer = new ContainerBuilder();

    // Mock a bunch of stuff that BusinessRulesProcessor requires but are
    // irrelevant to the tests in this class.
    $this->sutContainer->set('business_rules.util', $this->createMock(BusinessRulesUtil::class));
    $this->sutContainer->set('plugin.manager.business_rules.action', $this->createMock(BusinessRulesActionManager::class));
    $this->sutContainer->set('plugin.manager.business_rules.condition', $this->createMock(BusinessRulesConditionManager::class));
    $this->sutContainer->set('plugin.manager.business_rules.variable', $this->createMock(BusinessRulesVariableManager::class));
    $this->sutContainer->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    $this->sutContainer->set('messenger', $this->createMock(MessengerInterface::class));

    // Mock a class used by \Drupal\business_rules\Entity\BusinessRule, which is
    // constructed when BusinessRulesProcessor processes an event.
    $this->sutContainer->set('plugin.manager.business_rules.reacts_on', $this->createMock(BusinessRulesReactsOnManager::class));
  }

  /**
   * Tests that the BusinessRulesProcessor processes a request kernel event.
   *
   * @see \Drupal\business_rules\EventSubscriber\BusinessRulesListener
   */
  public function testProcessesMultipleEvents() {
    // Mock the first Business Rules Event, i.e.: the thing that triggers a
    // rule.
    $brEventOne = $this->createMock(BusinessRulesEvent::class);
    $brEventOne->expects($this->exactly(3))
      ->method('hasArgument')
      ->willReturnMap([
        ['loop_control', TRUE],
        ['variables', TRUE],
      ]);
    $brEventOne->expects($this->exactly(7))
      ->method('getArgument')
      ->willReturnMap([
        ['loop_control', 'node1'],
        ['reacts_on', ['id' => 'page_load']],
        ['entity_type_id', NULL],
        ['bundle', NULL],
      ]);

    // Mock the second Business Rules event.
    $brEventTwo = $this->createMock(BusinessRulesEvent::class);
    $brEventTwo->expects($this->exactly(1))
      ->method('hasArgument')
      ->willReturnMap([
        ['loop_control', TRUE],
        ['variables', TRUE],
      ]);
    $brEventTwo->expects($this->exactly(2))
      ->method('getArgument')
      ->willReturnMap([
        ['loop_control', 'node1'],
        ['reacts_on', ['id' => 'page_load']],
        ['entity_type_id', NULL],
        ['bundle', NULL],
      ]);

    // Mock a UUID generator.
    $uuidService = $this->createMock(Uuid::class);
    $uuidService->expects($this->exactly(1))
      ->method('generate')
      // RandomGenerator doesn't have a UUID generator; here's a random one.
      ->willReturn('b1e38d76-94a2-408b-977f-00a0f39ea73d');
    $this->sutContainer->set('uuid', $uuidService);

    // Mock global Business Rules configuration; and the config factory to
    // return it.
    $configObject = $this->createMock(ImmutableConfig::class);
    $configObject->expects($this->exactly(8))
      ->method('get')
      ->willReturnMap([
        ['enable_safemode', FALSE],
        ['clear_render_cache', FALSE],
      ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->exactly(1))
      ->method('get')
      ->with('business_rules.settings')
      ->willReturn($configObject);
    $this->sutContainer->set('config.factory', $configFactory);

    // Mock a Business Rule, and the classes to return data about it.
    $configStorage = $this->createMock(StorageInterface::class);
    $testRuleName = $this->randomMachineName();
    $testFqRuleName = sprintf('business_rules.business_rule.%s', $testRuleName);
    $configStorage->expects($this->exactly(1))
      ->method('listAll')
      ->with('business_rules.business_rule')
      ->willReturn([$testFqRuleName]);
    $configStorage->expects($this->exactly(1))
      ->method('readMultiple')
      ->with([$testFqRuleName])
      ->willReturn([
        $testFqRuleName => [
          // RandomGenerator doesn't have a UUID generator; here's a random one.
          'uuid' => '8a5154ff-e1c2-4526-83bb-4ee415cf1778',
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [],
          'description' => $this->randomGenerator->string(),
          'id' => $testRuleName,
          'label' => $this->randomGenerator->string(),
          'enabled' => TRUE,
          'reacts_on' => 'page_load',
          'items' => [],
          'tags' => [],
          'target_bundle' => NULL,
          'target_entity_type' => NULL,
        ],
      ]);
    $this->sutContainer->set('config.storage', $configStorage);

    // Mock an event dispatcher, and set expectations about which downstream
    // events will be triggered.
    $eventDispatcher = $this->createMock(EventDispatcher::class);
    $eventDispatcher->expects($this->exactly(6))
      ->method('dispatch')
      ->willReturnMap([
        [['business_rules.before_process_event', $brEventOne], $brEventOne],
        [['business_rules.before_check_the_triggered_rules', $brEventOne], $brEventOne],
        [['business_rules.after_check_the_triggered_rules', $brEventOne], $brEventOne],
        [['business_rules.after_process_event', $brEventOne], $brEventOne],
        [['business_rules.before_process_event', $brEventTwo], $brEventTwo],
        [['business_rules.before_check_the_triggered_rule', $brEventTwo], $brEventTwo],
        [['business_rules.after_check_the_triggered_rules', $brEventTwo], $brEventTwo],
        [['business_rules.after_process_event', $brEventTwo], $brEventTwo],
      ]);
    $this->sutContainer->set('event_dispatcher', $eventDispatcher);

    // The \Drupal\business_rules\Entity\BusinessRule::__construct() method uses
    // a Drupal service container directly, i.e.: instead of through proper
    // dependency injection.
    \Drupal::setContainer($this->sutContainer);

    // Instantiate a BusinessRulesProcessor, the class under test.
    $businessRulesProcessor = new BusinessRulesProcessor($this->sutContainer);

    // Try to initiate $brEventOne twice; and $brEventTwo once. The expectations
    // set earlier should throw an error if it processes $brEventOne more than
    // once.
    $businessRulesProcessor->process($brEventOne);
    $businessRulesProcessor->process($brEventTwo);
    $businessRulesProcessor->process($brEventOne);

    // Try to de-instantiate the class under test so its destructor runs.
    unset($businessRulesProcessor);
  }

}
