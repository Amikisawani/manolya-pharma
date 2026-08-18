<?php

namespace App\Domain\Catalog;

final class SampleMedicationCatalog
{
    /**
     * 50 médicaments d’officine (RDC), alignés sur le formulaire « Nouveau produit ».
     *
     * @return list<array{
     *     sku: string,
     *     commercial_name: string,
     *     generic_name: string,
     *     barcode: string,
     *     manufacturer: string,
     *     purchase_price: string,
     *     sale_price: string,
     *     currency_code: string,
     *     min_stock: string,
     *     critical_stock: string,
     *     allocation_strategy: string,
     *     category: string,
     *     description: string,
     *     initial_qty: string,
     *     lot_number: string,
     *     expires_at: string,
     *     warehouse: string
     * }>
     */
    public static function rows(): array
    {
        $expires = now()->addMonths(18)->toDateString();
        $warehouse = 'WH-MAIN';

        $items = [
            ['PARA-CP-500', 'Paracétamol 500 mg comprimé', 'Paracétamol', 'Antalgiques', '2500', '5000', 'Douleur et fièvre'],
            ['IBU-CP-400', 'Ibuprofène 400 mg comprimé', 'Ibuprofène', 'Antalgiques', '3200', '6500', 'Anti-inflammatoire'],
            ['ASP-CP-500', 'Acide acétylsalicylique 500 mg', 'Acide acétylsalicylique', 'Antalgiques', '1800', '3500', 'Douleur, fièvre, antiagrégant'],
            ['DCL-CP-50', 'Diclofénac 50 mg comprimé', 'Diclofénac', 'Antalgiques', '2800', '5500', 'Anti-inflammatoire'],
            ['TRM-CP-50', 'Tramadol 50 mg gélule', 'Tramadol', 'Antalgiques', '4500', '9000', 'Douleur modérée à intense'],
            ['AMOX-CP-500', 'Amoxicilline 500 mg gélule', 'Amoxicilline', 'Antibiotiques', '8500', '15000', 'Infections bactériennes'],
            ['AUGM-CP-1G', 'Amoxicilline / acide clavulanique 1 g', 'Amoxicilline + clavulanate', 'Antibiotiques', '12000', '22000', 'Infections respiratoires et ORL'],
            ['CIPRO-CP-500', 'Ciprofloxacine 500 mg comprimé', 'Ciprofloxacine', 'Antibiotiques', '7000', '13500', 'Infections urinaires et digestives'],
            ['AZITH-CP-500', 'Azithromycine 500 mg comprimé', 'Azithromycine', 'Antibiotiques', '6500', '12500', 'Infections ORL et pulmonaires'],
            ['METRO-CP-250', 'Métronidazole 250 mg comprimé', 'Métronidazole', 'Antibiotiques', '2200', '4500', 'Infections anaérobies et amibiase'],
            ['COTRI-CP-480', 'Cotrimoxazole 480 mg comprimé', 'Sulfaméthoxazole + triméthoprime', 'Antibiotiques', '1800', '3800', 'Infections urinaires et pulmonaires'],
            ['DOXY-CP-100', 'Doxycycline 100 mg gélule', 'Doxycycline', 'Antibiotiques', '3000', '6000', 'Infections et paludisme (selon protocole)'],
            ['CEFIX-CP-200', 'Céfixime 200 mg comprimé', 'Céfixime', 'Antibiotiques', '9000', '17000', 'Infections ORL et urinaires'],
            ['ERYTH-CP-500', 'Érythromycine 500 mg comprimé', 'Érythromycine', 'Antibiotiques', '4000', '8000', 'Alternative en cas d’allergie à la pénicilline'],
            ['FLUC-CP-150', 'Fluconazole 150 mg gélule', 'Fluconazole', 'Anti-infectieux', '3500', '7000', 'Candidoses'],
            ['ALB-CP-400', 'Albendazole 400 mg comprimé', 'Albendazole', 'Anti-infectieux', '1500', '3000', 'Vers intestinaux'],
            ['MEB-CP-100', 'Mébendazole 100 mg comprimé', 'Mébendazole', 'Anti-infectieux', '1200', '2500', 'Oxyures et ascaris'],
            ['ALU-CP-80', 'Artéméther / luméfantrine 80/480 mg', 'Artéméther + luméfantrine', 'Paludisme', '4500', '9000', 'Accès palustre simple'],
            ['QUIN-CP-300', 'Quinine 300 mg comprimé', 'Quinine', 'Paludisme', '2800', '5500', 'Paludisme selon protocole'],
            ['SRO-SACHET', 'Sels de réhydratation orale (sachet)', 'SRO', 'Gastro-entérologie', '400', '1000', 'Déshydratation'],
            ['MCP-CP-10', 'Métoclopramide 10 mg comprimé', 'Métoclopramide', 'Gastro-entérologie', '1500', '3200', 'Nausées et vomissements'],
            ['OME-CP-20', 'Oméprazole 20 mg gélule', 'Oméprazole', 'Gastro-entérologie', '2500', '5000', 'Ulcère et reflux'],
            ['RANI-CP-150', 'Ranitidine 150 mg comprimé', 'Ranitidine', 'Gastro-entérologie', '2000', '4200', 'Hyperacidité gastrique'],
            ['LOP-CP-2', 'Lopéramide 2 mg gélule', 'Lopéramide', 'Gastro-entérologie', '1200', '2500', 'Diarrhée aiguë'],
            ['SMECT-SACHET', 'Diosmectite sachet', 'Diosmectite', 'Gastro-entérologie', '800', '1800', 'Diarrhée et gastralgies'],
            ['VITC-CP-500', 'Vitamine C 500 mg comprimé', 'Acide ascorbique', 'Vitamines', '1500', '3000', 'Carence et asthénie'],
            ['VITB-CP', 'Vitamine B complexe comprimé', 'Vitamines B', 'Vitamines', '1800', '3800', 'Asthénie et neuropathies'],
            ['FER-FOL-CP', 'Fer + acide folique comprimé', 'Fer + acide folique', 'Vitamines', '2000', '4200', 'Anémie et grossesse'],
            ['CALC-CP-500', 'Calcium 500 mg comprimé', 'Calcium', 'Vitamines', '1600', '3400', 'Supplémentation calcique'],
            ['ZINC-CP-20', 'Zinc 20 mg comprimé', 'Zinc', 'Vitamines', '900', '2000', 'Diarrhée et carence'],
            ['SALB-CP-4', 'Salbutamol 4 mg comprimé', 'Salbutamol', 'Respiratoire', '1800', '3800', 'Asthme et bronchospasme'],
            ['PRED-CP-5', 'Prednisolone 5 mg comprimé', 'Prednisolone', 'Corticothérapie', '1500', '3200', 'Inflammation et allergie'],
            ['CETI-CP-10', 'Cétirizine 10 mg comprimé', 'Cétirizine', 'Allergologie', '1400', '3000', 'Rhinite et urticaire'],
            ['LORA-CP-10', 'Loratadine 10 mg comprimé', 'Loratadine', 'Allergologie', '1600', '3400', 'Allergie'],
            ['DEXA-CP-05', 'Dexaméthasone 0,5 mg comprimé', 'Dexaméthasone', 'Corticothérapie', '1200', '2600', 'Anti-inflammatoire stéroïdien'],
            ['GLIB-CP-5', 'Glibenclamide 5 mg comprimé', 'Glibenclamide', 'Cardiométabolique', '800', '1800', 'Diabète de type 2'],
            ['METF-CP-500', 'Metformine 500 mg comprimé', 'Metformine', 'Cardiométabolique', '1500', '3200', 'Diabète de type 2'],
            ['AMLO-CP-5', 'Amlodipine 5 mg comprimé', 'Amlodipine', 'Cardiométabolique', '1800', '3800', 'Hypertension'],
            ['CAPT-CP-25', 'Captopril 25 mg comprimé', 'Captopril', 'Cardiométabolique', '1200', '2600', 'Hypertension'],
            ['FURO-CP-40', 'Furosémide 40 mg comprimé', 'Furosémide', 'Cardiométabolique', '900', '2000', 'Œdèmes et HTA'],
            ['HCTZ-CP-25', 'Hydrochlorothiazide 25 mg comprimé', 'Hydrochlorothiazide', 'Cardiométabolique', '700', '1600', 'Hypertension'],
            ['NIFE-CP-20', 'Nifédipine 20 mg comprimé', 'Nifédipine', 'Cardiométabolique', '1400', '3000', 'Hypertension'],
            ['PARA-SYR-120', 'Paracétamol sirop 120 mg/5 ml', 'Paracétamol', 'Pédiatrie', '2800', '5500', 'Fièvre et douleur enfant'],
            ['AMOX-SYR-250', 'Amoxicilline sirop 250 mg/5 ml', 'Amoxicilline', 'Pédiatrie', '6500', '12000', 'Infections pédiatriques'],
            ['IBU-SYR-100', 'Ibuprofène sirop 100 mg/5 ml', 'Ibuprofène', 'Pédiatrie', '3500', '7000', 'Fièvre et douleur enfant'],
            ['NACL-500', 'Sérum physiologique 500 ml', 'Chlorure de sodium 0,9 %', 'Dispositifs', '2500', '5000', 'Perfusion et lavage'],
            ['PVP-SOL-10', 'Povidone iodée 10 % solution', 'Povidone iodée', 'Dermatologie', '3200', '6500', 'Antiseptique cutané'],
            ['BETA-CR-15', 'Bétaméthasone crème 15 g', 'Bétaméthasone', 'Dermatologie', '2800', '5800', 'Dermatoses inflammatoires'],
            ['CLOT-CR-20', 'Clotrimazole crème 20 g', 'Clotrimazole', 'Dermatologie', '2500', '5200', 'Mycoses cutanées'],
            ['CARBO-SYR', 'Carbocistéine sirop 5 %', 'Carbocistéine', 'Respiratoire', '3000', '6200', 'Toux grasse'],
        ];

        $rows = [];
        foreach ($items as $i => $item) {
            $n = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $rows[] = [
                'sku' => $item[0],
                'commercial_name' => $item[1],
                'generic_name' => $item[2],
                'barcode' => '86900000'.$n,
                'manufacturer' => 'Generic Pharma',
                'purchase_price' => $item[4],
                'sale_price' => $item[5],
                'currency_code' => 'CDF',
                'min_stock' => '20',
                'critical_stock' => '8',
                'allocation_strategy' => 'fefo',
                'category' => $item[3],
                'description' => $item[6],
                'initial_qty' => (string) (40 + ($i % 5) * 10),
                'lot_number' => 'LOT-'.$item[0].'-01',
                'expires_at' => $expires,
                'warehouse' => $warehouse,
            ];
        }

        return $rows;
    }
}
