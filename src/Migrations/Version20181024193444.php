<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181024193444 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('TRUNCATE TABLE migration_versions');
        $this->addSql('TRUNCATE TABLE answer');
        $this->addSql('TRUNCATE TABLE question');
        $this->addSql('TRUNCATE TABLE student_class');
        $this->addSql('TRUNCATE TABLE test');
        $this->addSql('TRUNCATE TABLE notification');
        $this->addSql('TRUNCATE TABLE filter_category');
        $this->addSql('TRUNCATE TABLE assign_test');
        $this->addSql('TRUNCATE TABLE question_test');
        $this->addSql('TRUNCATE TABLE question_filters');
        $this->addSql('TRUNCATE TABLE passed_question');
        $this->addSql('TRUNCATE TABLE student_class');
        $this->addSql('TRUNCATE TABLE test_filter');
        $this->addSql('TRUNCATE TABLE user');
        $this->addSql('TRUNCATE TABLE school');

        // Insert school in database
        $this->addSql("INSERT INTO `school` (id, `name`, logo, created_at, updated_at)
                            VALUES
                            (1, 'ExamsPM', 'http://api.testingministry.com/images/exampsPM_logo.png', NOW(), NOW()),
                            (2, 'Education Edge - Mississauga', 'http://api.testingministry.com/images/education_edge_logo.png', NOW(), NOW()),
                            (3, 'PMI Toronto Chapter', 'http://api.testingministry.com/images/pmitoronto-logo.png', NOW(), NOW()),
                            (4, 'Sixth Dimension Learning', 'http://api.testingministry.com/images/sixth-dimension-logo.png', NOW(), NOW()),
                            (5, 'Option Train College', 'http://api.testingministry.com/images/option_train_college_logo.png', NOW(), NOW()),
                            (6, 'HiTech Institute', 'http://api.testingministry.com/images/hitech_logo.jpg', NOW(), NOW())
                            ");

        // Insert filter categories data in database
        $this->addSql("
                INSERT INTO `user` (`id`, `email`, `first_name`, `last_name`, `username`, `type`, `status`, `confirm_token`, `password`, `is_enabled`, `salt`, `roles`, created_at, updated_at)
                 VALUES 
                 (1, 'test1@gmail.com', 'USER', 'USER', 'test1@gmail.com', 1, 0, 'sdfsdfsdf', 'zN5=', 1, 'sdNgi#Z', '[\"ROLE_STUDENT\"]', NOW(), NOW()),
                 (2, 'test2@gmail.com', 'John', 'Smith', 'test2@gmail.com', 1, 0, 'test2222', 'zNTj5/=', 1, 'yvNg7M#Z', '[\"ROLE_STUDENT\"]', NOW(), NOW()),
                 (3, 'test3@gmail.com', 'Test', 'Test', 'test3@gmail.com', 1, 0, 'test3333', 'zNT3j5/=', 1, 'yvNg7M3#Z', '[\"ROLE_STUDENT\"]', NOW(), NOW())
        ");

        // Insert filter categories data in database
        $this->addSql("INSERT INTO `filter_category` (id, `name`, created_at, updated_at)  
                            VALUES 
                            (1, 'Process Group', NOW(), NOW()), 
                            (2, 'Knowledge Area', NOW(), NOW()),
                            (3, 'Difficulty', NOW(), NOW())
                            ");

        // Insert filters data in database
        $this->addSql("INSERT INTO `test_filter` (id, `name`, id_filter_category, created_at, updated_at)  
                            VALUES 
                            (1, 'People', 1, NOW(), NOW()), 
                            (2, 'Process', 1, NOW(), NOW()),
                            (3, 'Business Environment', 1, NOW(), NOW()),
                            (6, 'Project Integration Management', 2, NOW(), NOW()),
                            (7, 'Project Scope Management', 2, NOW(), NOW()),
                            (9, 'Project Schedule Management', 2, NOW(), NOW()), 
                            (10, 'Project Cost Management', 2, NOW(), NOW()),
                            (11, 'Project Quality Management', 2, NOW(), NOW()),
                            (12, 'Project Resource Management', 2, NOW(), NOW()),
                            (13, 'Project Communications Management', 2, NOW(), NOW()),
                            (14, 'Project Risk Management', 2, NOW(), NOW()),
                            (15, 'Project Procurement Management', 2, NOW(), NOW()),
                            (16, 'Project Stakeholder Management', 2, NOW(), NOW()),
                            (17, 'Professional Responsibility', 2, NOW(), NOW()),
                            (18, 'Project Framework', 2, NOW(), NOW()),
                            (19, 'Easy', 3, NOW(), NOW()),
                            (20, 'Medium', 3, NOW(), NOW()),
                            (21, 'Hard', 3, NOW(), NOW())
                            ");


        // Insert questions data in database
        $this->addSql("INSERT INTO `question` (id, `name`, explanation, `number`, created_at, updated_at)  
                            VALUES 
                            (1, 'Question number 1?', 'Explanation 1', 1, NOW(), NOW()), 
                            (2, 'Question number 2?', 'Explanation 2', 2, NOW(), NOW()),
                            (3, 'Question number 3?', 'Explanation 3', 3, NOW(), NOW())
                            ");


        // Insert questions data in database
        $this->addSql("INSERT INTO `answer` (id, id_question, `name`, is_right, created_at, updated_at)  
                            VALUES 
                            (1, 1, 'answer 1', 0, NOW(), NOW()), 
                            (2, 1, 'answer 2', 0, NOW(), NOW()), 
                            (3, 1, 'answer 3', 1, NOW(), NOW()), 
                            (4, 1, 'answer 4', 0, NOW(), NOW()), 
                            (5, 2, 'answer 5', 1, NOW(), NOW()), 
                            (6, 2, 'answer 6', 0, NOW(), NOW()), 
                            (7, 2, 'answer 7', 0, NOW(), NOW()), 
                            (8, 2, 'answer 8', 0, NOW(), NOW()), 
                            (9, 3, 'answer 9', 1, NOW(), NOW()), 
                            (10, 3, 'answer 10', 0, NOW(), NOW()), 
                            (11, 3, 'answer 11', 0, NOW(), NOW()), 
                            (12, 3, 'answer 12', 0, NOW(), NOW())
                            ");

        // Relating questions with filters
        $this->addSql('INSERT INTO `question_filters` (id_question, id_filter)  
                            VALUES 
                            (1, 2), 
                            (1, 6),
                            (2, 6),
                            (2, 10),
                            (3, 7),
                            (1, 18),
                            (3, 18)
                            ');

        // Insert filter categories data in database
        $this->addSql("
                INSERT INTO `notification` (id, is_read, link, id_user, created_at, updated_at)
                 VALUES 
                 (1 , 0, 'http://www.testingministry.com/', 1, NOW(), NOW()),
                 (2 , 0, 'http://www.testingministry.com/', 1, NOW(), NOW()),
                 (3 , 0, 'http://www.testingministry.com/', 1, NOW(), NOW())
        ");

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
    }
}
