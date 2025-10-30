<?php

namespace App\Http\Livewire;

use App\Models\GovernmentDepartment;
use Livewire\Component;

class GovernmentDetails extends Component
{
    public $isGovernment;
    public $departments;
    public $patient;
    public $selected = '';

    public function render()
    {
        return view('livewire.government-details');
    }


}
