<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'adresse email ne peut pas être vide.")]
    #[Assert\Email(message: "L'adresse email {{ value }} n'est pas valide.")]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = ['ROLE_PATIENT'];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom ne peut pas être vide.")]
    #[Assert\Length(min: 2, max: 50, minMessage: "Le nom est trop court.")]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le prénom ne peut pas être vide.")]
    #[Assert\Length(min: 2, max: 50, minMessage: "Le prénom est trop court.")]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    #[Assert\Regex(
        pattern: "/^[0-9]{8}$/",
        message: "Le numéro de téléphone doit comporter exactement 8 chiffres."
    )]
    private ?string $tel = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'adresse ne peut pas être vide.")]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "L'adresse du cabinet ne peut pas dépasser 255 caractères.")]
    private ?string $cabinet = null;
   
   #[ORM\Column(type: 'datetime')]
   private ?\DateTimeInterface $createdAt = null;


    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DossierMedical $dossierMedical = null;

    #[ORM\Column]
    private bool $isVerified = false;
    #[ORM\Column]
    private bool $isActive = true;


    
    // --- RELATIONS MASTER ---

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: Suivi::class, cascade: ['persist', 'remove'])]
    private Collection $suivis;

    #[ORM\OneToMany(mappedBy: 'medecin', targetEntity: Intervention::class)]
    private Collection $interventions;

    // --- RELATIONS COMMUNES ---

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Commande::class, orphanRemoval: true)]
    private Collection $commandes;

    /**
     * @var Collection<int, RendezVous>
     */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'patient')]
    private Collection $rendezVouses;

    /**
     * @var Collection<int, RendezVous>
     */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'medecin')]
    private Collection $rendezVousMedecin;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'patient')]
    private Collection $conversationsAsPatient;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'medecin')]
    private Collection $conversationsAsMedecin;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $messages;

    // --- RELATIONS FORUM (AJOUTS) ---

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Post::class, orphanRemoval: true)]
    private Collection $posts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;
    
    public function __construct()
    {
        // Initialisations existantes (Master + Commun)
        $this->suivis = new ArrayCollection();
        $this->interventions = new ArrayCollection();
        $this->conversationsAsPatient = new ArrayCollection();
        $this->conversationsAsMedecin = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->rendezVouses = new ArrayCollection();
        $this->rendezVousMedecin = new ArrayCollection();
        $this->commandes = new ArrayCollection();

        // Initialisations Forum (Ajout)
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    // --- GETTERS & SETTERS STANDARDS ---
     public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void { }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getTel(): ?string { return $this->tel; }
    public function setTel(string $tel): static { $this->tel = $tel; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(string $adresse): static { $this->adresse = $adresse; return $this; }

    public function getCabinet(): ?string { return $this->cabinet; }
    public function setCabinet(?string $cabinet): static { $this->cabinet = $cabinet; return $this; }

    public function getDossierMedical(): ?DossierMedical { return $this->dossierMedical; }
    public function setDossierMedical(DossierMedical $dossierMedical): static
    {
        if ($dossierMedical->getUser() !== $this) {
            $dossierMedical->setUser($this);
        }
        $this->dossierMedical = $dossierMedical;
        return $this;
    }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    // --- GETTERS & SETTERS MASTER (SUIVIS & INTERVENTIONS) ---

    public function getSuivis(): Collection { return $this->suivis; }
    public function addSuivi(Suivi $suivi): static
    {
        if (!$this->suivis->contains($suivi)) {
            $this->suivis->add($suivi);
            $suivi->setPatient($this);
        }
        return $this;
    }
    public function removeSuivi(Suivi $suivi): static
    {
        if ($this->suivis->removeElement($suivi)) {
            if ($suivi->getPatient() === $this) {
                $suivi->setPatient(null);
            }
        }
        return $this;
    }

    public function getInterventions(): Collection { return $this->interventions; }
    public function addIntervention(Intervention $intervention): static
    {
        if (!$this->interventions->contains($intervention)) {
            $this->interventions->add($intervention);
            $intervention->setMedecin($this);
        }
        return $this;
    }
    public function removeIntervention(Intervention $intervention): static
    {
        if ($this->interventions->removeElement($intervention)) {
            if ($intervention->getMedecin() === $this) {
                $intervention->setMedecin(null);
            }
        }
        return $this;
    }

    // --- GETTERS & SETTERS COMMUNS (COMMANDES, RDV, MESSAGES) ---

    public function getCommandes(): Collection { return $this->commandes; }
    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setUser($this);
        }
        return $this;
    }
    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            if ($commande->getUser() === $this) {
                // orphanRemoval=true gère la suppression
            }
        }
        return $this;
    }

    public function getRendezVouses(): Collection { return $this->rendezVouses; }
    public function addRendezVouse(RendezVous $rendezVouse): static
    {
        if (!$this->rendezVouses->contains($rendezVouse)) {
            $this->rendezVouses->add($rendezVouse);
            $rendezVouse->setPatient($this);
        }
        return $this;
    }
    public function removeRendezVouse(RendezVous $rendezVouse): static
    {
        if ($this->rendezVouses->removeElement($rendezVouse)) {
            if ($rendezVouse->getPatient() === $this) {
                $rendezVouse->setPatient(null);
            }
        }
        return $this;
    }

    public function getRendezVousMedecin(): Collection { return $this->rendezVousMedecin; }
    public function addRendezVousMedecin(RendezVous $rendezVousMedecin): static
    {
        if (!$this->rendezVousMedecin->contains($rendezVousMedecin)) {
            $this->rendezVousMedecin->add($rendezVousMedecin);
            $rendezVousMedecin->setMedecin($this);
        }
        return $this;
    }
    public function removeRendezVousMedecin(RendezVous $rendezVousMedecin): static
    {
        if ($this->rendezVousMedecin->removeElement($rendezVousMedecin)) {
            if ($rendezVousMedecin->getMedecin() === $this) {
                $rendezVousMedecin->setMedecin(null);
            }
        }
        return $this;
    }

    public function getConversationsAsPatient(): Collection { return $this->conversationsAsPatient; }
    public function addConversationAsPatient(Conversation $conversation): static
    {
        if (!$this->conversationsAsPatient->contains($conversation)) {
            $this->conversationsAsPatient->add($conversation);
            $conversation->setPatient($this);
        }
        return $this;
    }
    public function removeConversationAsPatient(Conversation $conversation): static
    {
        if ($this->conversationsAsPatient->removeElement($conversation)) {
            if ($conversation->getPatient() === $this) {
                $conversation->setPatient(null);
            }
        }
        return $this;
    }

    public function getConversationsAsMedecin(): Collection { return $this->conversationsAsMedecin; }
    public function addConversationAsMedecin(Conversation $conversation): static
    {
        if (!$this->conversationsAsMedecin->contains($conversation)) {
            $this->conversationsAsMedecin->add($conversation);
            $conversation->setMedecin($this);
        }
        return $this;
    }
    public function removeConversationAsMedecin(Conversation $conversation): static
    {
        if ($this->conversationsAsMedecin->removeElement($conversation)) {
            if ($conversation->getMedecin() === $this) {
                $conversation->setMedecin(null);
            }
        }
        return $this;
    }

    public function getMessages(): Collection { return $this->messages; }
    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSender($this);
        }
        return $this;
    }
    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }
        return $this;
    }

    // --- GETTERS & SETTERS FORUM (AJOUTS) ---

    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setUser($this);
        }
        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getUser() === $this) {
                $post->setUser(null);
            }
        }
        return $this;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setUser($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getUser() === $this) {
                $comment->setUser(null);
            }
        }
        return $this;
    }
}