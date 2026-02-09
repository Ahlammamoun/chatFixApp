<?php

namespace App\Controller\Admin;

use App\Entity\Professional;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;


class ProfessionalCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Professional::class;
    }

    private function docLink(?string $path): string
    {
        if (!$path) return '-';

        // ✅ normalise si jamais tu stockes un chemin disque
        if (str_starts_with($path, '/home/')) {
            $pos = strpos($path, '/public');
            if ($pos !== false) {
                $path = substr($path, $pos + strlen('/public'));
            }
        }

        // ✅ s’assure d’avoir un chemin web
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">Voir</a>', $path);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('fullName', 'Nom');
        yield TextField::new('companyName', 'Société')->hideOnIndex();
        yield TextField::new('phoneNumber', 'Téléphone')->hideOnIndex();
        yield TextField::new('zone', 'Zone');
        yield TextField::new('postalCode', 'Code postal')->hideOnIndex();

        yield MoneyField::new('pricePerHour', 'Prix / heure')
            ->setCurrency('EUR')
            ->setStoredAsCents(false);

        yield BooleanField::new('availability', 'Disponible');

        yield AssociationField::new('speciality', 'Spécialité');
        yield TextareaField::new('assuranceDoc', 'Documents')
            ->onlyOnIndex()
            ->formatValue(function ($v, $entity) {
                /** @var \App\Entity\Professional $entity */
                $links = [];

                $links[] = 'Assurance: ' . $this->docLink($entity->getAssuranceDoc());
                $links[] = 'ID: ' . $this->docLink($entity->getIdentityDoc());
                $links[] = 'Titre: ' . $this->docLink($entity->getProTitleDoc());
                $links[] = 'RIB: ' . $this->docLink($entity->getRibDoc());

                return implode('<br>', $links);
            })
            ->renderAsHtml();


        // ✅ Photo profil (si tu as bien le champ profilePicture en DB)
        yield ImageField::new('profilePicture', 'Photo')
            ->setBasePath('/')        // car tu stockes /uploads/profiles/xxx.jpg
            ->onlyOnDetail();

        // ✅ Documents -> uniquement sur la page detail (plus clean)
        yield TextField::new('assuranceDoc', 'Assurance')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $this->docLink($v))
            ->renderAsHtml();

        yield TextField::new('identityDoc', 'Pièce d’identité')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $this->docLink($v))
            ->renderAsHtml();

        yield TextField::new('proTitleDoc', 'Titre pro')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $this->docLink($v))
            ->renderAsHtml();

        yield TextField::new('ribDoc', 'RIB (fichier)')
            ->onlyOnDetail()
            ->formatValue(fn($v) => $this->docLink($v))
            ->renderAsHtml();

        yield TextField::new('ribIban', 'IBAN')
            ->onlyOnDetail();
    }
}
