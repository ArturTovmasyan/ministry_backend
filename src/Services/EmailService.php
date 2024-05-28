<?php

namespace App\Services;

use App\Entity\ChallengeTest;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\Exception\Exception;
use Twig\Environment;
use SendGrid;

/**
 * Class EmailService
 * @package App\Services
 */
class EmailService
{
    public const FROM_EMAIL = 'ministry@testingministry.com';

    /** @var Environment $twig */
    protected $twig;

    /**
     * EmailService constructor.
     *
     * @param Environment $twig
     */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * This function is used to send email
     *
     * @param $data
     * @throws \Exception
     */
    public function sendEmail($data): void
    {
        // set from email
        $fromEmail = self::FROM_EMAIL;

        // init SendGrid
        $sendGrid = new SendGrid(getenv('SENDGRID_API_KEY'));
        $emailServer = new SendGrid\Mail\Mail();

        // generate html for email body
        $html = $this->twig->render('email/' . $data['type'] . '.html.twig', ['data' => $data]);
        $emailServer->setFrom($fromEmail);
        $emailServer->setSubject($data['subject']);
        $emailServer->addContent('text/html', $html);

        // add all student email
        if (\is_array($data['toEmail'])) {
            $emailServer->addTos($data['toEmail']);
        } else {
            $emailServer->addTo($data['toEmail']);
        }

        // Send email
        $sendGrid->send($emailServer);
    }

    /**
     * This function is used to send email for challenged students
     *
     * @param ChallengeTest $challengeTest
     * @throws Exception
     */
    public function sendEmailForChallengeStudents(ChallengeTest $challengeTest):void
    {
        // send email for challenge test students about win and lose
        $emailData = $this->getChallengeTestStudentsEmailData($challengeTest);

        try {
            if ($emailData['send'] === true) {
                if ($emailData && \array_key_exists('winner', $emailData)) {
                    $this->sendEmail($emailData['winner']);
                    $this->sendEmail($emailData['lose']);
                } elseif (\array_key_exists('equal', $emailData)) {
                    $this->sendEmail($emailData['equal']['first']);
                    $this->sendEmail($emailData['equal']['second']);
                }
            }

        } catch (\Exception $e) {
            throw new Exception('Mail error is: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * This function is used to generate challenge students data for send email
     *
     * @param ChallengeTest $challengeTest
     * @return array
     */
    private function getChallengeTestStudentsEmailData(ChallengeTest $challengeTest): array
    {
        $data = ['send' => true];
        $scoreBoardUrl = getenv('WEB_HOST') . '/dashboard/ranking';
        $competitorScore = $challengeTest->getCompetitorScore();
        $studentScore = $challengeTest->getStudentScore();

        /** @var User $student */
        $student = $challengeTest->getStudent();

        /** @var User $competitor */
        $competitor = $challengeTest->getCompetitor();

        $winnerData = [
            'subject' => 'You WON the challenge!',
            'type' => 'won-challenge-test',
            'backend_host' => getenv('BACKEND_HOST')
        ];

        $loseData = [
            'subject' => 'You lost the challenge!',
            'type' => 'lost-challenge-test',
            'backend_host' => getenv('BACKEND_HOST')
        ];

        $equalData = [
            'subject' => 'It’s a tie!',
            'type' => 'equal-challenge-test',
            'backend_host' => getenv('BACKEND_HOST')
        ];

        if ($studentScore === $competitorScore) {
            $data['equal']['first']['toEmail'] = $student->getEmail();
            $data['equal']['first']['student'] = $student->getFullName();
            $data['equal']['first']['scoreBoardUrl'] = $scoreBoardUrl;
            $data['equal']['first'] = array_merge($data['equal']['first'], $equalData);

            $data['equal']['second']['toEmail'] = $competitor->getEmail();
            $data['equal']['second']['student'] = $competitor->getFullName();
            $data['equal']['second']['scoreBoardUrl'] = $scoreBoardUrl;
            $data['equal']['second'] = array_merge($data['equal']['second'], $equalData);

        } elseif ($studentScore > $competitorScore) {
            $data['winner']['toEmail'] = $student->getEmail();
            $data['winner']['student'] = $student->getFullName();
            $data['winner']['scoreBoardUrl'] = $scoreBoardUrl;
            $data['winner'] = array_merge($data['winner'], $winnerData);

            $data['lose']['toEmail'] = $competitor->getEmail();
            $data['lose']['student'] = $competitor->getFullName();
            $data['lose']['scoreBoardUrl'] = $scoreBoardUrl;
            $data['lose'] = array_merge($data['lose'], $loseData);

        } elseif ($studentScore < $competitorScore) {
            $data['winner']['toEmail'] = $competitor->getEmail();
            $data['winner']['student'] = $competitor->getFullName();
            $data['winner']['scoreBoardUrl'] = $scoreBoardUrl;
            $data['winner'] = array_merge($data['winner'], $winnerData);

            $data['lose']['toEmail'] = $student->getEmail();
            $data['lose']['student'] = $student->getFullName();
            $data['lose']['scoreBoardUrl'] = $scoreBoardUrl;
            $data['lose'] = array_merge($data['lose'], $loseData);

        } else {
            return ['send' => false];
        }

        return $data;
    }
}