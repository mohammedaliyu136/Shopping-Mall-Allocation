<?php

/*
| Fixed clinical option lists used inside the action forms. Wards switch
| buttons and tabs on or off; they do not edit these lists.
*/

return [
    'obs_frequencies' => [1 => 'Hourly', 2 => '2-hourly', 4 => '4-hourly', 6 => '6-hourly', 8 => '8-hourly', 12 => '12-hourly'],

    'diets' => [
        'normal' => 'Normal diet', 'nbm' => 'Nil by mouth', 'sips' => 'Sips only', 'fluids' => 'Free fluids',
        'soft' => 'Soft diet', 'diabetic' => 'Diabetic diet', 'renal' => 'Renal diet', 'enteral' => 'Enteral feed (NG/PEG)',
    ],

    'routes' => ['PO' => 'Oral', 'IV' => 'Intravenous', 'IM' => 'Intramuscular', 'SC' => 'Subcutaneous', 'PR' => 'Rectal', 'NEB' => 'Nebulised', 'TOP' => 'Topical', 'SL' => 'Sublingual'],

    'frequencies' => ['OD' => 'Once daily', 'BD' => 'Twice daily', 'TDS' => 'Three times daily', 'QDS' => 'Four times daily', 'NOCTE' => 'At night', 'STAT' => 'Once only (STAT)', 'PRN' => 'As required (PRN)', 'CONT' => 'Continuous infusion'],

    'urgencies' => ['routine' => 'Routine', 'urgent' => 'Urgent', 'stat' => 'Immediate (STAT)'],

    'common_labs' => ['FBC', 'U&E', 'LFT', 'CRP', 'Coagulation screen', 'Group and save', 'Crossmatch', 'Blood cultures', 'Blood glucose', 'HbA1c', 'Troponin', 'ABG', 'Urine MCS', 'Malaria parasite', 'Sputum MCS'],

    'common_imaging' => ['Chest X-ray', 'Abdominal X-ray', 'CT head', 'CT abdomen/pelvis', 'CT pulmonary angiogram', 'Ultrasound abdomen', 'Echocardiogram', 'MRI'],

    'blood_products' => ['red_cells' => 'Red cells', 'platelets' => 'Platelets', 'ffp' => 'Fresh frozen plasma', 'cryo' => 'Cryoprecipitate'],

    'iv_fluids' => ['0.9% Sodium chloride', 'Hartmann\'s solution', '5% Glucose', '4% Glucose / 0.18% Saline', 'Plasma-Lyte 148'],

    'administration_reasons' => [
        'refused' => 'Patient refused', 'nbm' => 'Nil by mouth', 'unavailable' => 'Drug unavailable', 'off_ward' => 'Patient off ward',
        'asleep' => 'Patient asleep', 'vomiting' => 'Vomiting', 'clinical' => 'Held on clinical grounds', 'other' => 'Other',
    ],

    'consciousness' => ['A' => 'Alert', 'C' => 'New confusion', 'V' => 'Responds to voice', 'P' => 'Responds to pain', 'U' => 'Unresponsive'],

    'ventilation_modes' => ['none' => 'Not ventilated', 'hfno' => 'High-flow nasal oxygen', 'niv' => 'NIV / BiPAP', 'cpap' => 'CPAP', 'simv' => 'SIMV', 'pc' => 'Pressure control', 'ps' => 'Pressure support', 'vc' => 'Volume control'],

    'off_ward_destinations' => ['radiology' => 'Radiology / scan', 'theatre' => 'Theatre', 'endoscopy' => 'Endoscopy', 'cath_lab' => 'Cath lab', 'dialysis' => 'Dialysis unit', 'other' => 'Other department'],

    'discharge_checklists' => [
        'default' => [
            'meds_reconciled' => 'Discharge medication supplied and explained',
            'cannula_removed' => 'Cannula and lines removed',
            'summary_given' => 'Discharge summary given to patient',
            'follow_up_booked' => 'Follow-up appointment booked or explained',
            'transport_arranged' => 'Transport arranged',
            'belongings_returned' => 'Belongings and valuables returned',
        ],
        'facility' => [
            'meds_reconciled' => 'Medication and drug chart copied for receiving facility',
            'cannula_removed' => 'Lines reviewed and documented',
            'summary_given' => 'Transfer letter and summary sent with patient',
            'receiving_confirmed' => 'Receiving facility has confirmed the bed',
            'transport_arranged' => 'Ambulance / escort arranged',
            'belongings_returned' => 'Belongings sent with patient',
        ],
        'deceased' => [
            'death_verified' => 'Death verified and documented',
            'family_informed' => 'Next of kin informed',
            'last_offices' => 'Last offices performed',
            'certificate' => 'Medical certificate of cause of death requested',
            'mortuary' => 'Mortuary notified and patient transferred',
            'belongings_returned' => 'Belongings listed and returned to family',
        ],
    ],

    'risk_assessments' => [
        'falls' => ['label' => 'Falls', 'tool' => 'Morse Falls Scale', 'high_at' => 45, 'medium_at' => 25, 'max' => 125, 'every_hours' => 24],
        'pressure' => ['label' => 'Pressure', 'tool' => 'Waterlow score', 'high_at' => 15, 'medium_at' => 10, 'max' => 64, 'every_hours' => 24],
        'nutrition' => ['label' => 'Nutrition', 'tool' => 'MUST score', 'high_at' => 2, 'medium_at' => 1, 'max' => 6, 'every_hours' => 168],
    ],
];
