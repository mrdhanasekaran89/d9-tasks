<?php

namespace Drupal\Tests\business_rules\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests business rules support for paragraphs and AJAX.
 *
 * This test is similar to the test in DependentFieldsView, except the two
 * fields are in a paragraph in a node instead of just a node.
 *
 * @see \Drupal\Tests\business_rules\FunctionalJavascript\DependentFieldsView
 *
 * @group business_rules
 */
class ParagraphsAjaxSupport extends WebDriverTestBase {
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['business_rules_paragraphs_ajax_support'];

  /**
   * A set of taxonomy terms needed by the system under test.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $sutTerms;

  /**
   * A set of users needed by the system under test.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $sutUsers;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create terms in the vocabulary.
    $vocab = Vocabulary::load('vocab1');
    $this->assertInstanceOf(VocabularyInterface::class, $vocab, 'Test vocabulary can be loaded.');
    $this->sutTerms[] = $this->createTerm($vocab, ['name' => 'vocab1term1']);
    $this->assertInstanceOf(Term::class, $this->sutTerms[0], 'First test term can be created.');
    $this->sutTerms[] = $this->createTerm($vocab, ['name' => 'vocab1term2']);
    $this->assertInstanceOf(Term::class, $this->sutTerms[1], 'Second test term can be created.');

    // Create users tagged with those terms.
    $this->sutUsers[] = $this->drupalCreateUser([], 'user1', FALSE, [
      'field_user_term' => $this->sutTerms[0],
    ]);
    $this->sutUsers[] = $this->drupalCreateUser([], 'user2', FALSE, [
      'field_user_term' => $this->sutTerms[1],
    ]);
    $this->sutUsers[] = $this->drupalCreateUser([], 'user3', FALSE, [
      'field_user_term' => $this->sutTerms[0],
    ]);
    $this->sutUsers[] = $this->drupalCreateUser([], 'user4', FALSE, [
      'field_user_term' => $this->sutTerms[1],
    ]);
  }

  /**
   * Test that a triggering field can affect a target field in a paragraph.
   */
  public function testTriggeringFieldCanAffectTargetFieldInParagraph() {
    // Create a user that can add nodes.
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
    ]));

    // Load the node/add page containing the embedded paragraph.
    $this->drupalGet(Url::fromRoute('node.add', [
      'node_type' => 'nodetype1',
    ]));
    $page = $this->getSession()->getPage();

    // Get the fields we will be testing.
    $triggeringField = $page->find('named', ['select', 'paratype1_termfield1']);
    $targetFieldLabel = 'paratype1_user1';

    // Assert after selecting the first term in the triggering field, the first
    // and third users become options in the target field; but the second and
    // fourth users are not options in the target field.
    $triggeringField->selectOption($this->sutTerms[0]->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionExists($targetFieldLabel, $this->sutUsers[0]->label());
    $this->assertSession()->optionNotExists($targetFieldLabel, $this->sutUsers[1]->label());
    $this->assertSession()->optionExists($targetFieldLabel, $this->sutUsers[2]->label());
    $this->assertSession()->optionNotExists($targetFieldLabel, $this->sutUsers[3]->label());

    // Assert after selecting the second term in the triggering field, the
    // second and fourth terms become options in the target field; but the first
    // and third users are not options in the target field.
    $triggeringField->selectOption($this->sutTerms[1]->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionNotExists($targetFieldLabel, $this->sutUsers[0]->label());
    $this->assertSession()->optionExists($targetFieldLabel, $this->sutUsers[1]->label());
    $this->assertSession()->optionNotExists($targetFieldLabel, $this->sutUsers[2]->label());
    $this->assertSession()->optionExists($targetFieldLabel, $this->sutUsers[3]->label());
  }

}
