<?php

namespace Drupal\Tests\jobhunter_tester\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\job_hunter\Service\UserProfileService;
use Drupal\user\Entity\User;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Extended unit tests for UserProfileService.
 * 
 * Implements test case UPS-006 from TEST_CASES.md
 * (UPS-001 through UPS-005 already implemented in job_hunter module)
 * 
 * @group job_hunter
 * @group jobhunter_tester
 */
class UserProfileServiceExtendedTest extends UnitTestCase {

  /**
   * The user profile service under test.
   *
   * @var \Drupal\job_hunter\Service\UserProfileService
   */
  protected $userProfileService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->userProfileService = new UserProfileService();
  }

  /**
   * Test: Profile Statistics Generation (UPS-006)
   * 
   * Returns correct field counts (total, completed, missing).
   */
  public function testGetProfileStatsFieldCounts() {
    $user = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
      'field_professional_summary' => 'Test summary',
      'field_linkedin_url' => 'https://linkedin.com/in/test',
    ]);

    $stats = $this->userProfileService->getProfileStats($user);
    
    $this->assertIsArray($stats);
    $this->assertArrayHasKey('total_fields', $stats);
    $this->assertArrayHasKey('completed_fields', $stats);
    $this->assertArrayHasKey('missing_fields', $stats);
    
    $this->assertGreaterThan(0, $stats['total_fields']);
    $this->assertEquals(3, $stats['completed_fields']);
    $this->assertEquals($stats['total_fields'] - 3, $stats['missing_fields']);
  }

  /**
   * Test: Profile Statistics Generation (UPS-006)
   * 
   * Returns correct completeness percentage.
   */
  public function testGetProfileStatsCompletenessPercentage() {
    $user = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
      'field_professional_summary' => 'Test summary',
    ]);

    $stats = $this->userProfileService->getProfileStats($user);
    
    $this->assertArrayHasKey('completeness', $stats);
    $this->assertIsNumeric($stats['completeness']);
    $this->assertGreaterThanOrEqual(0, $stats['completeness']);
    $this->assertLessThanOrEqual(100, $stats['completeness']);
    
    // Should be greater than 0 since we have some fields filled
    $this->assertGreaterThan(0, $stats['completeness']);
  }

  /**
   * Test: Profile Statistics Generation (UPS-006)
   * 
   * Returns correct completeness status.
   */
  public function testGetProfileStatsCompletenessStatus() {
    // Test low completeness
    $user_low = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
    ]);
    $stats_low = $this->userProfileService->getProfileStats($user_low);
    
    $this->assertArrayHasKey('status', $stats_low);
    $this->assertIsArray($stats_low['status']);
    $this->assertArrayHasKey('class', $stats_low['status']);
    $this->assertArrayHasKey('level', $stats_low['status']);
    
    // Test high completeness
    $user_high = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
      'field_professional_summary' => 'Test summary',
      'field_linkedin_url' => 'https://linkedin.com/in/test',
      'field_github_url' => 'https://github.com/test',
      'field_portfolio_url' => 'https://portfolio.test',
      'field_work_authorization' => 'us_citizen',
      'field_phone' => '555-1234',
      'field_location' => 'Test City',
    ]);
    $stats_high = $this->userProfileService->getProfileStats($user_high);
    
    $this->assertEquals('complete', $stats_high['status']['class']);
    $this->assertEquals('high', $stats_high['status']['level']);
  }

  /**
   * Test: Profile Statistics Generation (UPS-006)
   * 
   * Returns valid recommendations list.
   */
  public function testGetProfileStatsRecommendations() {
    $user = $this->createMockUser([
      'field_professional_summary' => 'Test summary',
    ]);

    $stats = $this->userProfileService->getProfileStats($user);
    
    $this->assertArrayHasKey('recommendations', $stats);
    $this->assertIsArray($stats['recommendations']);
    $this->assertNotEmpty($stats['recommendations']);
    
    // Should include resume recommendation since it's missing
    $recommendations_text = implode(' ', $stats['recommendations']);
    $this->assertStringContainsString('resume', strtolower($recommendations_text));
  }

  /**
   * Test: Profile Statistics Generation (UPS-006)
   * 
   * Empty profile returns correct statistics.
   */
  public function testGetProfileStatsEmptyProfile() {
    $user = $this->createMockUser([]);

    $stats = $this->userProfileService->getProfileStats($user);
    
    $this->assertEquals(0, $stats['completeness']);
    $this->assertEquals(0, $stats['completed_fields']);
    $this->assertGreaterThan(0, $stats['missing_fields']);
    $this->assertEquals('incomplete', $stats['status']['class']);
    $this->assertEquals('low', $stats['status']['level']);
    $this->assertNotEmpty($stats['recommendations']);
  }

  /**
   * Test: Profile Statistics Generation (UPS-006)
   * 
   * Fully completed profile returns correct statistics.
   */
  public function testGetProfileStatsFullProfile() {
    $user = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
      'field_professional_summary' => 'Comprehensive professional summary',
      'field_linkedin_url' => 'https://linkedin.com/in/test',
      'field_github_url' => 'https://github.com/test',
      'field_portfolio_url' => 'https://portfolio.test',
      'field_work_authorization' => 'us_citizen',
      'field_phone' => '555-1234',
      'field_location' => 'Test City, State',
      'field_skills' => 'PHP, Drupal, JavaScript, React',
      'field_certifications' => 'Certified Developer',
    ]);

    $stats = $this->userProfileService->getProfileStats($user);
    
    $this->assertEquals(100, $stats['completeness']);
    $this->assertEquals($stats['total_fields'], $stats['completed_fields']);
    $this->assertEquals(0, $stats['missing_fields']);
    $this->assertEquals('complete', $stats['status']['class']);
    $this->assertEquals('high', $stats['status']['level']);
  }

  /**
   * Test: Profile completeness calculation edge cases.
   */
  public function testProfileCompletenessEdgeCases() {
    // Test with only optional fields filled
    $user_optional = $this->createMockUser([
      'field_github_url' => 'https://github.com/test',
      'field_portfolio_url' => 'https://portfolio.test',
    ]);
    $completeness_optional = $this->userProfileService->calculateProfileCompleteness($user_optional);
    
    $this->assertGreaterThan(0, $completeness_optional);
    $this->assertLessThan(50, $completeness_optional);
    
    // Test with only required fields filled
    $user_required = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
      'field_work_authorization' => 'us_citizen',
    ]);
    $completeness_required = $this->userProfileService->calculateProfileCompleteness($user_required);
    
    $this->assertGreaterThan($completeness_optional, $completeness_required);
  }

  /**
   * Test: Field priority in recommendations.
   */
  public function testRecommendationsPriority() {
    $user = $this->createMockUser([]);

    $recommendations = $this->userProfileService->getMissingFieldRecommendations($user, 10);
    
    $this->assertIsArray($recommendations);
    $this->assertNotEmpty($recommendations);
    
    // Resume should be in top recommendations
    $first_three = array_slice($recommendations, 0, 3);
    $first_three_text = implode(' ', $first_three);
    $this->assertStringContainsString('resume', strtolower($first_three_text));
  }

  /**
   * Creates a mock user entity with specified field values.
   *
   * @param array $field_values
   *   Array of field values.
   *
   * @return \Drupal\user\Entity\User|\PHPUnit\Framework\MockObject\MockObject
   *   Mock user entity.
   */
  protected function createMockUser(array $field_values) {
    $user = $this->createMock(User::class);

    // Mock hasField method
    $user->method('hasField')->willReturnCallback(function($field_name) use ($field_values) {
      return array_key_exists($field_name, $field_values) || 
             in_array($field_name, array_keys(UserProfileService::FIELD_WEIGHTS));
    });

    // Mock get method
    $user->method('get')->willReturnCallback(function($field_name) use ($field_values) {
      $field_item_list = $this->createMock(FieldItemListInterface::class);
      
      if (array_key_exists($field_name, $field_values)) {
        $field_item_list->method('isEmpty')->willReturn(false);
        
        if (in_array($field_name, ['field_portfolio_url', 'field_linkedin_url', 'field_github_url'])) {
          $field_item_list->uri = $field_values[$field_name];
        } else {
          $field_item_list->value = $field_values[$field_name];
        }
      } else {
        $field_item_list->method('isEmpty')->willReturn(true);
        $field_item_list->value = null;
        $field_item_list->uri = null;
      }
      
      return $field_item_list;
    });

    return $user;
  }

}
