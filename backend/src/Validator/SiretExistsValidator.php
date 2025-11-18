<?php
namespace App\Validator;

use App\Service\SiretValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SiretExistsValidator extends ConstraintValidator
{
    public function __construct(private SiretValidator $siretValidator) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value) return;

        // Vérifie que le format est bien 14 chiffres
        if (!preg_match('/^\d{14}$/', (string)$value)) {
            $this->context->buildViolation('❌ Le numéro SIRET doit contenir exactement 14 chiffres.')
                ->addViolation();
            return;
        }

        // ✅ Contrôle de validité Luhn (version corrigée)
        if (!$this->isValidLuhn((string)$value)) {
            $this->context->buildViolation('⚠️ Le numéro SIRET fourni est invalide (échec du contrôle Luhn).')
                ->addViolation();
            return;
        }

        // ✅ Vérifie via API Sirene
        $result = $this->siretValidator->checkSiret((string)$value);

        if (!$result['valid']) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ siret }}', (string)$value)
                ->addViolation();
                return;
        }
        // ✅ Si SIRET valide → on récupère automatiquement le nom de l’entreprise
        if ($result['valid'] && isset($result['data']['uniteLegale']['denominationUniteLegale'])) {
            $companyName = $result['data']['uniteLegale']['denominationUniteLegale'];

            $object = $this->context->getObject();
            if (method_exists($object, 'setCompanyName')) {
                $object->setCompanyName($companyName);
            }
        }
    }

    /**
     * Vérifie la validité d’un SIRET avec l’algorithme de Luhn
     */
    private function isValidLuhn(string $siret): bool
    {
        $sum = 0;
        $alt = false;

        // Lecture de droite à gauche
        for ($i = strlen($siret) - 1; $i >= 0; $i--) {
            $n = intval($siret[$i]);
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return ($sum % 10) === 0;
    }
}
