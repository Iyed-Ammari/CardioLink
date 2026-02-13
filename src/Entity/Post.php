<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Comment;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAT = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private int $likes = 0;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post')]
    private Collection $comments;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: "post_likes")]
    private Collection $likedBy;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->likedBy = new ArrayCollection();
    }

    // ====================== GETTERS & SETTERS ======================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAT(): ?\DateTimeImmutable
    {
        return $this->createdAT;
    }

    public function setCreatedAT(\DateTimeImmutable $createdAT): static
    {
        $this->createdAT = $createdAT;
        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }
   public function getImage(): ?string
    {
    return $this->image;
     }

public function setImage(?string $image): static
    {
    $this->image = $image;
    return $this;
    }


    // ====================== LIKE SYSTEM ======================

    /**
     * @return Collection<int, User>
     */
    public function getLikedBy(): Collection
    {
        return $this->likedBy;
    }

    public function addLikedBy(User $user): static
    {
        if (!$this->likedBy->contains($user)) {
            $this->likedBy->add($user);
            $this->likes++;
        }

        return $this;
    }

    public function removeLikedBy(User $user): static
    {
        if ($this->likedBy->contains($user)) {
            $this->likedBy->removeElement($user);
            if ($this->likes > 0) {
                $this->likes--;
            }
        }

        return $this;
    }
    /**
 * Vérifie si l'utilisateur a déjà liké ce post
 */
public function isLikedByUser(?User $user): bool
{
    if (!$user) {
        return false; // utilisateur non connecté
    }

    return $this->likedBy->contains($user);
}

    // ====================== COMMENTS ======================

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }

        return $this;
    }
}