<?php

namespace App\Controller\Admin;

use App\Entity\Offer;
use App\Entity\Professional;
use App\Entity\Rating;
use App\Entity\SupportTicket;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Persistence\ManagerRegistry;


#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private ManagerRegistry $doctrine) {}
    public function index(): Response
    {
        // ✅ redirige vers la liste Users au lieu de la page Welcome
        $url = $this->container->get(AdminUrlGenerator::class)
            ->setRoute('admin_user_index')
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ChatFix Admin');
    }

    public function configureMenuItems(): iterable
    {
        $stats = $this->getStats();

        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Comptes');
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);
        yield MenuItem::linkToCrud('Professionnels', 'fa fa-briefcase', Professional::class);

        yield MenuItem::section('Business');

        // ✅ Offres avec badge CA total
        yield MenuItem::linkToCrud('Offres', 'fa fa-file', Offer::class)
            ->setBadge(number_format($stats['totalCa'], 2, ',', ' ') . ' €', 'success');

        yield MenuItem::linkToCrud('Notes', 'fa fa-star', Rating::class);

        yield MenuItem::section('Support');
        yield MenuItem::linkToCrud('Tickets', 'fa fa-life-ring', SupportTicket::class);

        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out');
    }


    private function getStats(): array
    {
        $conn = $this->doctrine->getConnection();

        $row = $conn->fetchAssociative("
        SELECT 
            COALESCE(SUM(price),0) as total_ca,
            COUNT(*) as nb_paid
        FROM offer
        WHERE status = 'paid'
          AND paid_at IS NOT NULL
    ");

        return [
            'totalCa' => (float) $row['total_ca'],
            'nbPaid'  => (int) $row['nb_paid'],
        ];
    }
}
