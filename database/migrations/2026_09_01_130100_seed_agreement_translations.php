<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The agreement moved out of the frontend message catalogs and into the CMS
     * so staff can edit it. English lives in config/site_content.php as the
     * default, but a translation has nowhere to fall back to except English, so
     * the Spanish and French wording that already existed is carried over here.
     *
     * A section somebody has already saved is left alone.
     */
    public function up(): void
    {
        foreach ($this->translations() as $locale => $content) {
            $exists = DB::table('site_contents')
                ->where('section', 'agreement')
                ->where('locale', $locale)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('site_contents')->insert([
                'section' => 'agreement',
                'locale' => $locale,
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('site_contents')
            ->where('section', 'agreement')
            ->whereIn('locale', ['es', 'fr'])
            ->delete();
    }

    protected function translations(): array
    {
        return [
            'es' => [
                'intro' => 'Lee lo siguiente antes de firmar.',
                'terms_body' => '<p>Al enviar esta solicitud certifico que toda la información facilitada es verdadera, completa y exacta según mi leal saber y entender. Entiendo que cualquier declaración falsa o engañosa puede suponer la denegación de mi solicitud o la resolución del contrato de arrendamiento que se derive de ella.</p><p>Autorizo a Sobi y a sus representantes a verificar la información facilitada en esta solicitud, incluidos entre otros mi historial laboral, mis ingresos y mi historial de alquiler, y a obtener un informe de crédito y una comprobación de antecedentes penales a través de una agencia externa.</p><p>Entiendo que cualquier tasa de solicitud abonada no es reembolsable, sea cual sea el resultado de la solicitud, y que presentar esta solicitud no garantiza su aprobación ni la reserva de la vivienda.</p><p>Si se aprueba, me comprometo a firmar un contrato de arrendamiento formal y a cumplir todas sus condiciones y las normas de la comunidad antes de la entrada.</p><p>Reconozco que Sobi puede ponerse en contacto conmigo por teléfono, correo electrónico o mensaje de texto en relación con el estado de esta solicitud.</p>',
                'certify' => 'Certifico que la información facilitada en esta solicitud es verdadera y completa, y autorizo a Sobi a verificarla y a obtener un informe de crédito y una comprobación de antecedentes.',
            ],
            'fr' => [
                'intro' => 'Lisez ce qui suit avant de signer.',
                'terms_body' => '<p>En envoyant ce dossier, je certifie que toutes les informations fournies sont véridiques, complètes et exactes à ma connaissance. Je comprends que toute déclaration fausse ou trompeuse peut entraîner le refus de mon dossier ou la résiliation du bail qui en découlerait.</p><p>J\'autorise Sobi et ses représentants à vérifier les informations fournies dans ce dossier, y compris mon parcours professionnel, mes revenus et mon historique locatif, et à obtenir un rapport de solvabilité et une vérification des antécédents judiciaires auprès d\'un organisme tiers.</p><p>Je comprends que les frais de dossier versés ne sont pas remboursables, quelle que soit l\'issue de la demande, et que l\'envoi de ce dossier ne garantit ni son acceptation ni la réservation du logement.</p><p>En cas d\'acceptation, je m\'engage à signer un bail formel et à respecter l\'ensemble de ses conditions ainsi que le règlement de la résidence avant l\'emménagement.</p><p>Je reconnais que Sobi peut me contacter par téléphone, e-mail ou SMS au sujet de l\'avancement de ce dossier.</p>',
                'certify' => 'Je certifie que les informations fournies dans ce dossier sont véridiques et complètes, et j\'autorise Sobi à les vérifier et à obtenir un rapport de solvabilité et une vérification des antécédents.',
            ],
        ];
    }
};
