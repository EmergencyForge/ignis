<?php
try {
    // Default medications from the existing hardcoded list
    // Dosierungen contain value + unit (e.g., "100 mg") - unit is auto-selected when dosage is chosen
    $defaultMedikamente = [
        ['wirkstoff' => 'Acetylsalicylsäure', 'herstellername' => 'ASS', 'dosierungen' => '100 mg,250 mg,500 mg', 'priority' => 1],
        ['wirkstoff' => 'Adenosin', 'herstellername' => NULL, 'dosierungen' => '6 mg,12 mg', 'priority' => 2],
        ['wirkstoff' => 'Alteplase', 'herstellername' => 'Actilyse', 'dosierungen' => '10 mg,50 mg', 'priority' => 3],
        ['wirkstoff' => 'Amiodaron', 'herstellername' => 'Cordarex', 'dosierungen' => '150 mg,300 mg', 'priority' => 4],
        ['wirkstoff' => 'Atropinsulfat', 'herstellername' => 'Atropin', 'dosierungen' => '0.5 mg,1 mg', 'priority' => 5],
        ['wirkstoff' => 'Butylscopolamin', 'herstellername' => 'Buscopan', 'dosierungen' => '20 mg', 'priority' => 6],
        ['wirkstoff' => 'Calciumgluconat', 'herstellername' => NULL, 'dosierungen' => '1 g,2 g', 'priority' => 7],
        ['wirkstoff' => 'Ceftriaxon', 'herstellername' => 'Rocephin', 'dosierungen' => '1 g,2 g', 'priority' => 8],
        ['wirkstoff' => 'Dimenhydrinat', 'herstellername' => 'Vomex', 'dosierungen' => '62 mg,150 mg', 'priority' => 9],
        ['wirkstoff' => 'Dimetinden', 'herstellername' => 'Fenistil', 'dosierungen' => '4 mg', 'priority' => 10],
        ['wirkstoff' => 'Epinephrin', 'herstellername' => 'Adrenalin', 'dosierungen' => '0.1 mg,0.3 mg,0.5 mg,1 mg', 'priority' => 11],
        ['wirkstoff' => 'Esketamin', 'herstellername' => 'Ketanest S', 'dosierungen' => '0.125 mg,0.25 mg,0.5 mg', 'priority' => 12],
        ['wirkstoff' => 'Fentanyl', 'herstellername' => NULL, 'dosierungen' => '0.05 mg,0.1 mg,0.2 mg', 'priority' => 13],
        ['wirkstoff' => 'Flumazenil', 'herstellername' => 'Anexate', 'dosierungen' => '0.2 mg,0.5 mg', 'priority' => 14],
        ['wirkstoff' => 'Furosemid', 'herstellername' => 'Lasix', 'dosierungen' => '20 mg,40 mg,80 mg', 'priority' => 15],
        ['wirkstoff' => 'Gelatinepolysuccinat', 'herstellername' => 'Gelafundin', 'dosierungen' => '500 ml,1000 ml', 'priority' => 16],
        ['wirkstoff' => 'Glukose', 'herstellername' => NULL, 'dosierungen' => '10 g,20 g,40 g', 'priority' => 17],
        ['wirkstoff' => 'Heparin', 'herstellername' => NULL, 'dosierungen' => '5000 IE,10000 IE', 'priority' => 18],
        ['wirkstoff' => 'Humanblut', 'herstellername' => NULL, 'dosierungen' => NULL, 'priority' => 19],
        ['wirkstoff' => 'Hydroxycobolamin', 'herstellername' => 'Cyanokit', 'dosierungen' => '5 g', 'priority' => 20],
        ['wirkstoff' => 'Ipratropiumbromid', 'herstellername' => 'Atrovent', 'dosierungen' => '0.25 mg,0.5 mg', 'priority' => 21],
        ['wirkstoff' => 'Lidocain', 'herstellername' => 'Xylocain', 'dosierungen' => '50 mg,100 mg', 'priority' => 22],
        ['wirkstoff' => 'Lorazepam', 'herstellername' => 'Tavor', 'dosierungen' => '1 mg,2 mg,4 mg', 'priority' => 23],
        ['wirkstoff' => 'Metamizol', 'herstellername' => 'Novalgin', 'dosierungen' => '1 g,2.5 g', 'priority' => 24],
        ['wirkstoff' => 'Metoprolol', 'herstellername' => 'Beloc', 'dosierungen' => '5 mg', 'priority' => 25],
        ['wirkstoff' => 'Midazolam', 'herstellername' => 'Dormicum', 'dosierungen' => '1 mg,2.5 mg,5 mg', 'priority' => 26],
        ['wirkstoff' => 'Morphin', 'herstellername' => NULL, 'dosierungen' => '2 mg,5 mg,10 mg', 'priority' => 27],
        ['wirkstoff' => 'Naloxon', 'herstellername' => 'Narcanti', 'dosierungen' => '0.4 mg,0.8 mg', 'priority' => 28],
        ['wirkstoff' => 'Natriumhydrogencarbonat', 'herstellername' => NULL, 'dosierungen' => '50 ml,100 ml', 'priority' => 29],
        ['wirkstoff' => 'Natriumthiosulfat', 'herstellername' => NULL, 'dosierungen' => '12.5 g', 'priority' => 30],
        ['wirkstoff' => 'Nitroglycerin', 'herstellername' => 'Nitrolingual', 'dosierungen' => '0.4 mg,0.8 mg', 'priority' => 31],
        ['wirkstoff' => 'Norepinephrin', 'herstellername' => 'Arterenol', 'dosierungen' => '0.05 mg,0.1 mg,1 mg', 'priority' => 32],
        ['wirkstoff' => 'Ondansetron', 'herstellername' => 'Zofran', 'dosierungen' => '4 mg,8 mg', 'priority' => 33],
        ['wirkstoff' => 'Paracetamol', 'herstellername' => 'Perfalgan', 'dosierungen' => '500 mg,1000 mg', 'priority' => 34],
        ['wirkstoff' => 'Piritramid', 'herstellername' => 'Dipidolor', 'dosierungen' => '3.75 mg,7.5 mg,15 mg', 'priority' => 35],
        ['wirkstoff' => 'Prednisolon', 'herstellername' => 'Solu-Decortin', 'dosierungen' => '25 mg,50 mg,100 mg,250 mg', 'priority' => 36],
        ['wirkstoff' => 'Propofol', 'herstellername' => 'Disoprivan', 'dosierungen' => '20 mg,50 mg,100 mg,200 mg', 'priority' => 37],
        ['wirkstoff' => 'Reproterol', 'herstellername' => 'Bronchospasmin', 'dosierungen' => '0.09 mg', 'priority' => 38],
        ['wirkstoff' => 'Rocuronium', 'herstellername' => 'Esmeron', 'dosierungen' => '50 mg,100 mg', 'priority' => 39],
        ['wirkstoff' => 'Salbutamol', 'herstellername' => 'Sultanol', 'dosierungen' => '0.1 mg,0.2 mg,2.5 mg,5 mg', 'priority' => 40],
        ['wirkstoff' => 'Succinylcholin', 'herstellername' => 'Lysthenon', 'dosierungen' => '100 mg', 'priority' => 41],
        ['wirkstoff' => 'Sufentanil', 'herstellername' => NULL, 'dosierungen' => '5 mcg,10 mcg,25 mcg', 'priority' => 42],
        ['wirkstoff' => 'Theodrenalin-Cafedrin', 'herstellername' => 'Akrinor', 'dosierungen' => '0.5 ml,1 ml,2 ml', 'priority' => 43],
        ['wirkstoff' => 'Thiopental', 'herstellername' => 'Trapanal', 'dosierungen' => '250 mg,500 mg', 'priority' => 44],
        ['wirkstoff' => 'Tranexamsäure', 'herstellername' => 'Cyklokapron', 'dosierungen' => '1 g', 'priority' => 45],
        ['wirkstoff' => 'Urapidil', 'herstellername' => 'Ebrantil', 'dosierungen' => '12.5 mg,25 mg,50 mg', 'priority' => 46],
        ['wirkstoff' => 'Vollelektrolytlösung', 'herstellername' => NULL, 'dosierungen' => '250 ml,500 ml,1000 ml', 'priority' => 47],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO intra_edivi_medikamente (wirkstoff, herstellername, dosierungen, priority, active) VALUES (:wirkstoff, :herstellername, :dosierungen, :priority, 1)");

    foreach ($defaultMedikamente as $med) {
        $stmt->execute([
            ':wirkstoff' => $med['wirkstoff'],
            ':herstellername' => $med['herstellername'],
            ':dosierungen' => $med['dosierungen'],
            ':priority' => $med['priority']
        ]);
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
