<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Die Standard-Quicklinks "Fahrzeuginfo" und "Administration" standen als
 * fahrzeuginfo.php und ../index.php in der Tabelle. Beide Übersichten geben
 * die URL so aus, wie sie ist: unter /enotf-v2/overview führte
 * fahrzeuginfo.php auf /enotf-v2/fahrzeuginfo, das es nicht gibt.
 * ../enotf/fahrzeuginfo und ../ stimmen von /enotf/ und /enotf-v2/ aus und
 * unabhängig von BASE_PATH. Geändert werden nur Einträge mit genau den
 * alten Werten, selbst angelegte oder angepasste Links bleiben.
 */
class UpdateIntraEnotfQuicklinks07102026Paths extends AbstractMigration
{
    private const PATHS = [
        'fahrzeuginfo.php' => '../enotf/fahrzeuginfo',
        '../index.php'     => '../',
    ];

    public function up(): void
    {
        foreach (self::PATHS as $old => $new) {
            $this->execute(sprintf(
                'UPDATE intra_enotf_quicklinks SET url = %s WHERE url = %s',
                $this->getAdapter()->getConnection()->quote($new),
                $this->getAdapter()->getConnection()->quote($old),
            ));
        }
    }

    public function down(): void
    {
        foreach (self::PATHS as $old => $new) {
            $this->execute(sprintf(
                'UPDATE intra_enotf_quicklinks SET url = %s WHERE url = %s',
                $this->getAdapter()->getConnection()->quote($old),
                $this->getAdapter()->getConnection()->quote($new),
            ));
        }
    }
}
