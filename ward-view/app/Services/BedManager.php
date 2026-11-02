<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\WardConfig;

/**
 * Keeps a ward's beds in line with its configured layout and count. Beds are
 * never deleted while occupied: reducing the count deactivates empty beds and
 * leaves occupied ones in place until the patient moves.
 */
class BedManager
{
    public function sync(Ward $ward, WardConfig $config): void
    {
        // Labels follow position, so each position always maps to one label.
        $layout = $config->bed_layout;
        $existing = $ward->beds()->withCount('occupant')->get()->keyBy('position');

        for ($i = 1; $i <= $config->bed_count; $i++) {
            $label = $layout->bedPrefix().$i;
            $group = $layout->groupName().' '.(intdiv($i - 1, $layout->groupSize()) + 1);
            $bed = $existing->get($i);

            if ($bed) {
                $bed->update(['label' => $label, 'group_label' => $group, 'is_active' => true]);
            } else {
                $ward->beds()->create(['label' => $label, 'group_label' => $group, 'position' => $i]);
            }
        }

        $existing->filter(fn (Bed $b) => $b->position > $config->bed_count)
            ->each(fn (Bed $b) => $b->update(['is_active' => $b->occupant_count > 0]));
    }
}
