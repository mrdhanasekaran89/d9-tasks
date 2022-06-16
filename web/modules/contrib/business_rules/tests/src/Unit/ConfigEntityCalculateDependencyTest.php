<?php

namespace Drupal\Tests\business_rules\Unit;

use Drupal\business_rules\BusinessRulesItemObject;
use Drupal\business_rules\Entity\Action;
use Drupal\business_rules\Entity\BusinessRule;
use Drupal\business_rules\Entity\Condition;
use Drupal\business_rules\Entity\Variable;
use Drupal\business_rules\Plugin\BusinessRulesAction\ActionSet;
use Drupal\business_rules\Plugin\BusinessRulesActionManager;
use Drupal\business_rules\Plugin\BusinessRulesCondition\LogicalAnd;
use Drupal\business_rules\Plugin\BusinessRulesCondition\UserHasRole;
use Drupal\business_rules\Plugin\BusinessRulesCondition\UserVariableHasRole;
use Drupal\business_rules\Plugin\BusinessRulesConditionManager;
use Drupal\business_rules\Plugin\BusinessRulesReactsOnManager;
use Drupal\business_rules\Plugin\BusinessRulesVariableManager;
use Drupal\business_rules\Util\BusinessRulesProcessor;
use Drupal\business_rules\Util\BusinessRulesUtil;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Test dependencies calculation.
 *
 * @group business_rules
 */
class ConfigEntityCalculateDependencyTest extends UnitTestCase {

  /**
   * The Business rules Action plugin manager.
   *
   * @var \Drupal\business_rules\Plugin\BusinessRulesActionManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $businessRulesActionManager;

  /**
   * The Business rules Condition plugin manager.
   *
   * @var \Drupal\business_rules\Plugin\BusinessRulesConditionManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $businessRulesConditionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->businessRulesActionManager = $this->createMock(BusinessRulesActionManager::class);
    $this->businessRulesConditionManager = $this->createMock(BusinessRulesConditionManager::class);

    $container = new ContainerBuilder();
    $container->set('uuid', $this->createMock(UuidInterface::class));
    $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
    $container->set('config.factory', $this->createMock(ConfigFactoryInterface::class));
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('entity_type.repository', $this->createMock(EntityTypeRepositoryInterface::class));
    $container->set('business_rules.processor', $this->createMock(BusinessRulesProcessor::class));
    $container->set('business_rules.util', $this->createMock(BusinessRulesUtil::class));
    $container->set('plugin.manager.business_rules.action', $this->businessRulesActionManager);
    $container->set('plugin.manager.business_rules.condition', $this->businessRulesConditionManager);
    $container->set('plugin.manager.business_rules.reacts_on', $this->createMock(BusinessRulesReactsOnManager::class));
    $container->set('plugin.manager.business_rules.variable', $this->createMock(BusinessRulesVariableManager::class));
    $businessRulesUtil = $this->createMock(BusinessRulesUtil::class);
    $businessRulesUtil->container = $container;
    $container->set('business_rules.util', $businessRulesUtil);
    \Drupal::setContainer($container);

    $actionAConfigEntity = $this->createMock(Action::class);
    $actionAConfigEntity->expects($this->any())
      ->method('getConfigDependencyName')
      ->willReturn('business_rules.action.test_action_a');

    $actionBConfigEntity = $this->createMock(Action::class);
    $actionBConfigEntity->expects($this->any())
      ->method('getConfigDependencyName')
      ->willReturn('business_rules.action.test_action_b');

    $conditionAConfigEntity = $this->createMock(Condition::class);
    $conditionAConfigEntity->expects($this->any())
      ->method('getConfigDependencyName')
      ->willReturn('business_rules.condition.test_condition_a');

    $conditionBConfigEntity = $this->createMock(Condition::class);
    $conditionBConfigEntity->expects($this->any())
      ->method('getConfigDependencyName')
      ->willReturn('business_rules.condition.test_condition_b');

    $variableConfigEntity = $this->createMock(Variable::class);
    $variableConfigEntity->expects($this->any())
      ->method('getConfigDependencyName')
      ->willReturn('business_rules.variable.test_variable');

    $entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->any())
      ->method('load')
      ->willReturnMap([
        ['test_action_a', $actionAConfigEntity],
        ['test_action_b', $actionBConfigEntity],
        ['test_condition_a', $conditionAConfigEntity],
        ['test_condition_b', $conditionBConfigEntity],
        ['test_variable', $variableConfigEntity],
    ]);
  }

  /**
   * @covers \Drupal\business_rules\Entity\BusinessRule::calculateDependencies
   */
  public function testBusinessRuleCalculateDependencies() {
    $business_rule = new BusinessRule([]);
    $dependencies = $business_rule->calculateDependencies()->getDependencies();
    $this->assertArrayNotHasKey('config', $dependencies);

    $dependencyA = new BusinessRulesItemObject('test_action_a', 'action', 0);
    $business_rule->addItem($dependencyA);
    $dependencies = $business_rule->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a'], $dependencies['config']);

    $dependencyB = new BusinessRulesItemObject('test_condition_a', 'condition', 0);
    $business_rule->addItem($dependencyB);
    $dependencies = $business_rule->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a', 'business_rules.condition.test_condition_a'], $dependencies['config']);
  }

  /**
   * @covers \Drupal\business_rules\Entity\Condition::calculateDependencies
   */
  public function testActionCalculateDependencies() {
    $this->businessRulesActionManager->expects($this->any())
      ->method('getDefinition')
      ->willReturnMap([
        ['action_set', TRUE, ['class' => ActionSet::class, 'id' => 'action_set']],
    ]);

    $action = new Action(['type' => 'action_set',]);
    $dependencies = $action->calculateDependencies()->getDependencies();
    $this->assertArrayNotHasKey('config', $dependencies);

    $action->setSetting('items', [
      'test_action_a' => [
        'weight' => 0,
        'type' => 'action',
        'id' => 'test_action_a',
      ],
    ]);
    $dependencies = $action->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a'], $dependencies['config']);

    $action->setSetting('items', [
      'test_action_a' => [
        'weight' => 0,
        'type' => 'action',
        'id' => 'test_action_a',
      ],
      'test_action_b' => [
        'weight' => 0,
        'type' => 'action',
        'id' => 'test_action_b',
      ],
    ]);
    $dependencies = $action->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a', 'business_rules.action.test_action_b'], $dependencies['config']);
  }


  /**
   * @covers \Drupal\business_rules\Entity\Condition::calculateDependencies
   */
  public function testConditionCalculateDependencies() {
    $this->businessRulesConditionManager->expects($this->any())
      ->method('getDefinition')
      ->willReturnMap([
        ['user_has_role', TRUE, ['class' => UserHasRole::class, 'id' => 'user_has_role']],
        ['logical_and', TRUE, ['class' => LogicalAnd::class, 'id' => 'logical_and']],
    ]);

    $condition = new Condition(['type' => 'user_has_role',]);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertArrayNotHasKey('config', $dependencies);

    $dependencyA = new BusinessRulesItemObject('test_action_a', 'action', 0);
    $condition->addSuccessItem($dependencyA);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a'], $dependencies['config']);

    $dependencyB = new BusinessRulesItemObject('test_condition_a', 'condition', 0);
    $condition->addFailItem($dependencyB);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a', 'business_rules.condition.test_condition_a'], $dependencies['config']);

    $condition = new Condition(['type' => 'logical_and',]);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertArrayNotHasKey('config', $dependencies);

    $condition->setSetting('items', [
      'test_condition_a' => [
        'weight' => 0,
        'type' => 'condition',
        'id' => 'test_condition_a',
      ],
    ]);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.condition.test_condition_a'], $dependencies['config']);

    $condition->setSetting('items', [
      'test_condition_a' => [
        'weight' => 0,
        'type' => 'condition',
        'id' => 'test_condition_a',
      ],
      'test_condition_b' => [
        'weight' => 0,
        'type' => 'condition',
        'id' => 'test_condition_b',
      ],
    ]);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.condition.test_condition_a', 'business_rules.condition.test_condition_b'], $dependencies['config']);
  }

  /**
   * @covers \Drupal\business_rules\Entity\BusinessRulesItemBase::calculateDependencies
   */
  public function testBusinessRulesItemObjectCalculateDependencies() {
    $this->businessRulesConditionManager->expects($this->any())
      ->method('getDefinition')
      ->willReturnMap([
        ['user_variable_has_role', TRUE, ['class' => UserVariableHasRole::class, 'id' => 'user_variable_has_role']],
    ]);

    $condition = new Condition([
      'type' => 'user_variable_has_role',
      'settings' => [
        'user_variable' => 'test_variable',
      ],
    ]);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.variable.test_variable'], $dependencies['config']);

    $dependencyA = new BusinessRulesItemObject('test_action_a', 'action', 0);
    $condition->addSuccessItem($dependencyA);
    $dependencies = $condition->calculateDependencies()->getDependencies();
    $this->assertEquals(['business_rules.action.test_action_a', 'business_rules.variable.test_variable'], $dependencies['config']);
  }

}
