<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\TimeAwareTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NotificationRepository")
 */
class Notification
{
    use TimeAwareTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint")
     * @Assert\Choice(choices={0, 1}, message="Invalid isRead value. Please set 0 or 1")
     * @Assert\NotBlank()
     */
    private $isRead = 0;

    /**
     * @ORM\Column(type="string", length=40)
     * @Assert\Url(message="Invalid notification url")
     */
    private $link = '';

    /**
     * @var User
     *
     * @Assert\Count(
     *      min = 1,
     *      minMessage = "Notification must be assigned to {{ limit }} user"
     * )
     * @ORM\ManyToOne(targetEntity="User", inversedBy="notification", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_user", referencedColumnName="id", nullable=false)
     * })
     */
    private $user;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getIsRead(): ?int
    {
        return $this->isRead;
    }

    /**
     * @param int $isRead
     * @return Notification
     */
    public function setIsRead(int $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param string $link
     * @return Notification
     */
    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return Notification
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
