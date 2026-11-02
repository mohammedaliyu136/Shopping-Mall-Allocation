<?php

namespace App\Ward;

use App\Enums\DnrStatus;
use App\Enums\EntryCategory;
use App\Models\Encounter;
use App\Models\WardConfig;
use Carbon\CarbonInterface;

/**
 * Status lives in the header, not the button bar: DNR, allergies, critical
 * flag, risk assessments due, open alerts, pending transfers and obs due.
 */
class PatientHeader
{
    public function __construct(public Encounter $encounter, public WardConfig $config) {}

    /** @return list<array{key:string,label:string,tone:string,edit:?string,title:?string}> */
    public function badges(): array
    {
        $e = $this->encounter;
        $p = $e->patient;
        $badges = [];

        $badges[] = [
            'key' => 'dnr', 'edit' => 'resus',
            'label' => $p->dnr_status->label(),
            'tone' => match ($p->dnr_status) {
                DnrStatus::Dnacpr => 'purple',
                DnrStatus::NotRecorded => 'amber',
                default => 'grey',
            },
            'title' => 'Tap to edit resuscitation status',
        ];

        $badges[] = [
            'key' => 'allergies', 'edit' => 'allergies',
            'label' => $p->hasAllergies() ? 'Allergies: '.implode(', ', $p->allergies) : (empty($p->allergies) ? 'Allergies not recorded' : 'No known drug allergies'),
            'tone' => $p->hasAllergies() ? 'red' : (empty($p->allergies) ? 'amber' : 'grey'),
            'title' => 'Tap to edit allergies',
        ];

        if ($p->critical_flag) {
            $badges[] = ['key' => 'critical', 'edit' => 'critical', 'label' => 'Critical: '.($p->critical_reason ?: 'flagged'), 'tone' => 'red', 'title' => 'Tap to edit critical flag'];
        } else {
            $badges[] = ['key' => 'critical', 'edit' => 'critical', 'label' => 'Not flagged critical', 'tone' => 'ghost', 'title' => 'Tap to flag as critical'];
        }

        $alerts = $this->openAlerts();
        if ($alerts) {
            $badges[] = ['key' => 'alerts', 'edit' => null, 'label' => $alerts.' open alert'.($alerts > 1 ? 's' : '').' for doctor', 'tone' => 'red', 'title' => 'See Patient History'];
        }

        if ($t = $e->pendingTransfer) {
            $badges[] = ['key' => 'transfer', 'edit' => null, 'label' => 'Transfer pending → '.$t->toWard?->name, 'tone' => 'blue', 'title' => $t->reason];
        }

        if ($e->off_ward) {
            $badges[] = ['key' => 'off_ward', 'edit' => null, 'label' => 'Off ward: '.$e->off_ward, 'tone' => 'blue', 'title' => null];
        }

        if ($e->isDischargePending()) {
            $badges[] = ['key' => 'discharge', 'edit' => null, 'label' => 'Discharge in progress', 'tone' => 'green', 'title' => null];
        }

        if ($obs = $this->obsStatus()) {
            $badges[] = $obs;
        }

        foreach ($this->riskAssessments() as $risk) {
            $badges[] = $risk;
        }

        return $badges;
    }

    public function openAlerts(): int
    {
        return $this->encounter->entries()->where('category', EntryCategory::Alert->value)->where('status', 'open')->count();
    }

    public function obsStatus(): ?array
    {
        $last = $this->encounter->latestObservation;
        if (! $last) {
            return ['key' => 'obs', 'edit' => null, 'label' => 'No observations yet', 'tone' => 'amber', 'title' => null];
        }
        $due = $this->encounter->obsDueAt($this->config);
        $news = 'NEWS2 '.$last->score;
        $tone = match ($last->status) {
            'high' => 'red', 'medium' => 'red', 'low-medium' => 'amber', default => 'grey',
        };

        if ($due->isPast()) {
            return ['key' => 'obs', 'edit' => null, 'label' => "{$news} · obs overdue ".$due->diffForHumans(short: true, syntax: CarbonInterface::DIFF_ABSOLUTE), 'tone' => 'red', 'title' => 'Last recorded '.$last->recorded_at->format('H:i')];
        }

        return ['key' => 'obs', 'edit' => null, 'label' => "{$news} · obs due ".$due->format('H:i'), 'tone' => $tone, 'title' => 'Last recorded '.$last->recorded_at->format('H:i')];
    }

    /** Falls, pressure and nutrition assessments: due/overdue badges for nurses. */
    public function riskAssessments(): array
    {
        if (! $this->config->ward_type->admitsPatients()) {
            return [];
        }

        $latest = $this->encounter->riskAssessments()->orderByDesc('completed_at')->get()->unique('kind')->keyBy('kind');
        $badges = [];

        foreach (config('ward.risk_assessments') as $kind => $def) {
            $a = $latest->get($kind);
            if (! $a) {
                $badges[] = ['key' => "risk_{$kind}", 'edit' => "risk:{$kind}", 'label' => "{$def['label']} assessment due", 'tone' => 'amber', 'title' => $def['tool']];
            } elseif ($a->next_due_at->isPast()) {
                $badges[] = ['key' => "risk_{$kind}", 'edit' => "risk:{$kind}", 'label' => "{$def['label']} assessment overdue", 'tone' => 'red', 'title' => $def['tool'].' last '.$a->completed_at->format('j M H:i')];
            } elseif ($a->risk_level !== 'low') {
                $badges[] = ['key' => "risk_{$kind}", 'edit' => "risk:{$kind}", 'label' => "{$def['label']} risk: {$a->risk_level}", 'tone' => $a->risk_level === 'high' ? 'red' : 'amber', 'title' => $def['tool'].' score '.$a->score.', next due '.$a->next_due_at->format('j M H:i')];
            }
        }

        return $badges;
    }
}
