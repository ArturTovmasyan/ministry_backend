<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\TimeAwareTrait;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Answer
 *
 * @ORM\Table(name="answer")
 * @ORM\Entity(repositoryClass="App\Repository\AnswerRepository")
 */
class Answer
{
    use TimeAwareTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"test", "pass-test", "test_by_assign_id"})
     */
    private $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=900)
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 800,
     *      maxMessage = "Answer cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"test", "pass-test", "test_by_assign_id"})
     */
    private $name;

    /**
     * @var boolean $rightAnswer
     *
     * @ORM\Column(name="is_right", type="boolean")
     * @Serializer\Groups({"pass-test"})
     */
    private $isRight = false;

    /**
     * @var Question
     *
     * @ORM\ManyToOne(targetEntity="Question", inversedBy="answer", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_question", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     * })
     */
    private $question;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Answer
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Answer
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRight(): bool
    {
        return $this->isRight;
    }

    /**
     * @param bool $isRight
     * @return Answer
     */
    public function setIsRight(bool $isRight): self
    {
        $this->isRight = $isRight;
        return $this;
    }

    /**
     * @return Question
     */
    public function getQuestion(): Question
    {
        return $this->question;
    }

    /**
     * @param Question $question
     * @return Answer
     */
    public function setQuestion(Question $question): self
    {
        $this->question = $question;
        return $this;
    }

}
