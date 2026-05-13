<?php

namespace App\Command;

use App\Repository\RendezVousRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:rappel-rdv', description: 'Envoie un mail de rappel J-1')]
class RappelRdvCommand extends Command
{
    public function __construct(
        private RendezVousRepository $repository,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // On cherche les RDV qui ont lieu demain
        $demain = (new \DateTime())->modify('+1 day');
        $rdvs = $this->repository->findRdvPourDemain($demain);

        foreach ($rdvs as $rdv) {
            $email = (new Email())
                ->from('notification@cardiolink.com')
                ->to($rdv->getPatient()->getEmail())
                ->subject('🔔 Rappel : Votre rendez-vous de demain')
                ->html("
                    <p>Bonjour {$rdv->getPatient()->getPrenom()},</p>
                    <p>Ceci est un rappel pour votre rendez-vous de demain le <strong>{$rdv->getDateHeure()->format('d/m/Y')}</strong> à <strong>{$rdv->getDateHeure()->format('H:i')}</strong>.</p>
                    <p>Médecin : Dr. {$rdv->getMedecin()->getNom()}</p>
                    " . ($rdv->getType() === 'Télémédecine' ? "<p>Lien visio : <a href='{$rdv->getLienVisio()}'>Cliquez ici pour rejoindre</a></p>" : "") . "
                ");

            $this->mailer->send($email);
        }

        $output->writeln(count($rdvs) . ' mails de rappel envoyés.');
        return Command::SUCCESS;
    }
}