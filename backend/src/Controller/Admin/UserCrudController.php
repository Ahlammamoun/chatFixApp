<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $export = Action::new('exportEmails', 'Exporter emails', 'fa fa-download')
            ->linkToRoute('admin_export_user_emails');

        return $actions
            ->add(\EasyCorp\Bundle\EasyAdminBundle\Config\Crud::PAGE_INDEX, $export);
    }

    #[Route('/admin/export/user-emails', name: 'admin_export_user_emails')]
    public function exportEmails(): Response
    {
        $rows = $this->em->createQueryBuilder()
            ->select('u.email')
            ->from(User::class, 'u')
            ->where('u.email IS NOT NULL')
            ->andWhere("u.email <> ''")
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $csv = "email\n";
        foreach ($rows as $r) {
            $email = str_replace('"', '""', $r['email']);
            $csv .= "\"{$email}\"\n";
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="client_emails.csv"',
        ]);
    }
}
