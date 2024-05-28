<?php

namespace App\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use App\Entity\Traits\TimeAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="blog", uniqueConstraints={@ORM\UniqueConstraint(name="IDX_duplicate_user_blog", columns={"title", "id_author"})})
 * @ORM\Entity(repositoryClass="App\Repository\BlogRepository")
 * @UniqueEntity(fields={"title", "author"}, message="Blog with this name already exist for current author")
 */
class Blog
{
    use TimeAwareTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(name="id", type="integer")
     * @Serializer\Groups({"blog"})
     */
    private $id;

    /**
     * @ORM\Column(name="title", type="string", length=255)
     * @Assert\NotBlank()
     * @Serializer\Groups({"blog"})
     */
    private $title;

    /**
     * @ORM\Column(name="content", type="text")
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 2000,
     *      maxMessage = "Content cannot be longer than {{ limit }} characters"
     * )
     * @Serializer\Groups({"blog"})
     */
    private $content;

    /**
     * @ORM\Column(name="is_draft", type="boolean", nullable=true)
     * @Assert\NotNull()
     * @Serializer\Groups({"blog"})
     */
    private $isDraft = false;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="blog", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_author", referencedColumnName="id", nullable=false)
     * })
     * @Serializer\Groups({"blog"})
     */
    private $author;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\Groups({"blog"})
     * @Serializer\Type("DateTime<'Y-m-d'>")
     */
    private $publishDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getIsDraft(): ?bool
    {
        return $this->isDraft;
    }

    public function setIsDraft(bool $isDraft): self
    {
        $this->isDraft = $isDraft;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor($author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getPublishDate(): ?\DateTimeInterface
    {
        return $this->publishDate;
    }

    public function setPublishDate(?\DateTimeInterface $publishDate): self
    {
        $this->publishDate = $publishDate;

        return $this;
    }

    /**
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("created_date")
     * @Serializer\Groups({"blog"})
     */
    public function getCreatedDate(): string
    {
        return $this->getCreatedAt()->format('Y-m-d');
    }
}
