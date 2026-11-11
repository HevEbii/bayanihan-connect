<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Message;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\DistributionRecorder;
use App\Services\VolunteerScheduler;
use App\Support\Role;
use Illuminate\Database\Seeder;

/**
 * Demo data for Bayanihan Connect. Every demo account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UnitSeeder::class);
        $unit = fn (string $name) => Unit::where('name', $name)->value('id');

        // ---- Accounts (one per role) ----
        $mk = fn ($name, $email, $role, $phone = null) => User::create([
            'name' => $name, 'email' => $email, 'role' => $role, 'phone' => $phone, 'password' => 'password', 'is_active' => true,
        ]);
        $admin = $mk('System Administrator', 'admin@bayanihan.test', Role::ADMIN);
        $director = $mk('Rosario Villanueva', 'director@bayanihan.test', Role::DIRECTOR);
        $maria = $mk('Maria Santos', 'coordinator@bayanihan.test', Role::COORDINATOR, '0917 555 0101');
        $jose = $mk('Jose Ramirez', 'jose.coordinator@bayanihan.test', Role::COORDINATOR, '0917 555 0102');
        $finance = $mk('Liza Mercado', 'finance@bayanihan.test', Role::FINANCE);
        $volUser = $mk('Paolo Reyes', 'volunteer@bayanihan.test', Role::VOLUNTEER, '0918 222 3344');
        $donorUser = $mk('Kapuso Partner Portal', 'donor@bayanihan.test', Role::DONOR);

        // ---- Programs & activities ----
        $programs = [
            'food' => Program::create(['name' => 'Noche Buena Food Pack Drive', 'category' => 'food', 'coordinator_id' => $maria->id,
                'start_date' => today()->subMonths(2), 'status' => 'active',
                'description' => 'Monthly food packs for low-income families in partner barangays.']),
            'relief' => Program::create(['name' => 'Typhoon Emergency Relief', 'category' => 'relief', 'coordinator_id' => $maria->id,
                'start_date' => today()->subMonth(), 'status' => 'active',
                'description' => 'Rapid relief for families displaced by flooding and typhoons.']),
            'education' => Program::create(['name' => 'Balik-Eskwela Learner Support', 'category' => 'education', 'coordinator_id' => $jose->id,
                'start_date' => today()->subMonths(3), 'status' => 'active',
                'description' => 'School supplies and tutoring for public elementary learners.']),
            'workshop' => Program::create(['name' => 'Kabuhayan Livelihood Workshops', 'category' => 'workshop', 'coordinator_id' => $jose->id,
                'start_date' => today()->subMonths(1), 'status' => 'active',
                'description' => 'Skills workshops on food processing and small business basics.']),
        ];

        $act = fn ($program, $title, $daysFromToday, $start, $end, $location, $slots, $status = null) => Activity::create([
            'program_id' => $program->id, 'title' => $title, 'activity_date' => today()->addDays($daysFromToday),
            'start_time' => $start.':00', 'end_time' => $end.':00', 'location' => $location, 'volunteer_slots' => $slots,
            'status' => $status ?? ($daysFromToday < 0 ? 'completed' : 'scheduled'),
            'description' => 'Meet at the Foundation van 30 minutes before start. Wear your Bayanihan ID.',
        ]);
        $a = [
            'pack1' => $act($programs['food'], 'Brgy. San Jose Food Pack Distribution', -20, '08:00', '12:00', 'San Jose Barangay Hall', 6),
            'pack2' => $act($programs['food'], 'Brgy. Malanday Food Pack Distribution', -6, '13:00', '17:00', 'Malanday Covered Court', 6),
            'pack3' => $act($programs['food'], 'Brgy. Tumana Food Pack Distribution', 5, '08:00', '12:00', 'Tumana Elementary School', 8),
            'relief1' => $act($programs['relief'], 'Evacuation Center Relief Operation', -10, '07:00', '15:00', 'Marikina Sports Center', 10),
            'relief2' => $act($programs['relief'], 'Relief Pack Assembly', 3, '09:00', '13:00', 'Foundation Warehouse', 12),
            'edu1' => $act($programs['education'], 'School Supplies Turnover', -14, '09:00', '11:30', 'Concepcion Elementary School', 5),
            'edu2' => $act($programs['education'], 'Saturday Reading Tutorial', 4, '08:00', '11:00', 'Foundation Learning Hub', 4),
            'work1' => $act($programs['workshop'], 'Longganisa Making Workshop', 9, '13:00', '16:00', 'Barangay Tumana Multi-purpose Hall', 3),
        ];

        // ---- Beneficiaries ----
        $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Luz', 'Carlos', 'Teresita', 'Ramon', 'Elena', 'Andres', 'Rosa',
            'Miguel', 'Carmen', 'Antonio', 'Gloria', 'Ricardo', 'Josefina', 'Ernesto', 'Lourdes', 'Danilo', 'Marites',
            'Rogelio', 'Imelda', 'Noel', 'Cristina', 'Jun', 'Rowena', 'Arnel', 'Divina', 'Ricky', 'Evangeline'];
        $last = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Aquino', 'Ramos', 'Villanueva', 'Castillo',
            'Flores', 'Gonzales', 'Torres', 'Navarro', 'Pascual', 'Domingo'];
        $beneficiaries = collect();
        foreach ($first as $i => $fn) {
            $program = match (true) { $i < 12 => $programs['food'], $i < 20 => $programs['relief'], default => $programs['education'] };
            $beneficiaries->push(Beneficiary::create([
                'first_name' => $fn, 'last_name' => $last[$i % count($last)],
                'barangay' => ['Brgy. San Jose', 'Brgy. Malanday', 'Brgy. Tumana', 'Brgy. Concepcion'][$i % 4],
                'household_size' => $program === $programs['education'] ? 1 : 3 + ($i % 5),
                'category' => $program === $programs['education'] ? 'learner' : ($i % 7 === 0 ? 'senior' : 'family'),
                'program_id' => $program->id, 'sex' => $i % 2 ? 'male' : 'female',
                'contact_number' => '09'.(17 + $i % 3).' '.(100 + $i).' '.(4000 + $i * 7),
                'created_by' => $program->coordinator_id, 'updated_by' => $program->coordinator_id,
            ]));
        }

        // ---- Donors & donations ----
        $donors = [
            'gma' => Donor::create(['name' => 'GMA Kapuso Foundation', 'type' => 'institution', 'contact_person' => 'Ella Cruz', 'email' => 'partners@kapuso.test', 'user_id' => $donorUser->id]),
            'clara' => Donor::create(['name' => 'Maria Clara Center', 'type' => 'institution', 'contact_person' => 'Sr. Agnes Lim', 'email' => 'mcc@example.test']),
            'sagip' => Donor::create(['name' => 'Sagip Kapamilya Partners', 'type' => 'institution', 'contact_person' => 'Ben Tan']),
            'phil' => Donor::create(['name' => 'Philippine Foundation Inc.', 'type' => 'institution', 'contact_person' => 'Grace Uy']),
            'rotary' => Donor::create(['name' => 'Rotary Club of Marikina', 'type' => 'institution', 'contact_person' => 'Tony Sy']),
            'lim' => Donor::create(['name' => 'Dr. Arturo Lim', 'type' => 'individual', 'email' => 'alim@example.test']),
            'grace' => Donor::create(['name' => 'Grace Fellowship Church', 'type' => 'institution']),
        ];
        $don = fn ($donor, $program, $type, $qty, $daysAgo, $item = null, $unitName = null, $activity = null) => Donation::create([
            'donor_id' => $donor->id, 'program_id' => $program->id, 'activity_id' => $activity?->id, 'type' => $type,
            'item_name' => $item, 'quantity' => $qty, 'unit_id' => $unitName ? $unit($unitName) : null,
            'received_at' => today()->subDays($daysAgo), 'recorded_by' => $finance->id,
        ]);
        $d = [
            'rice' => $don($donors['gma'], $programs['food'], 'in_kind', 1200, 30, 'Family Food Packs', 'Pack'),
            'cash1' => $don($donors['gma'], $programs['relief'], 'cash', 150000, 25),
            'water' => $don($donors['clara'], $programs['relief'], 'in_kind', 500, 18, 'Bottled Water', 'Box'),
            'hyg' => $don($donors['sagip'], $programs['relief'], 'in_kind', 800, 15, 'Hygiene Kits', 'Kit'),
            'cash2' => $don($donors['phil'], $programs['food'], 'cash', 80000, 12),
            'supplies' => $don($donors['rotary'], $programs['education'], 'in_kind', 300, 20, 'School Supply Kits', 'Kit'),
            'cash3' => $don($donors['lim'], $programs['education'], 'cash', 25000, 8),
            'cash4' => $don($donors['grace'], $programs['workshop'], 'cash', 40000, 5, null, null, $a['work1']),
            'rice2' => $don($donors['phil'], $programs['food'], 'in_kind', 60, 4, 'Rice (25kg)', 'Sack'),
        ];

        // ---- Distributions (through the same rules the app enforces) ----
        $recorder = app(DistributionRecorder::class);
        $byProgram = fn ($p) => $beneficiaries->where('program_id', $programs[$p]->id)->pluck('id');
        $recorder->record(['activity_id' => $a['pack1']->id, 'item_name' => 'Family Food Packs', 'quantity' => 450, 'unit_id' => $unit('Pack'),
            'distributed_at' => $a['pack1']->activity_date, 'beneficiaries' => $byProgram('food')->take(7)->all(),
            'funding' => [['donation_id' => $d['rice']->id, 'amount' => 450]]], $maria);
        $recorder->record(['activity_id' => $a['pack2']->id, 'item_name' => 'Family Food Packs', 'quantity' => 400, 'unit_id' => $unit('Pack'),
            'distributed_at' => $a['pack2']->activity_date, 'beneficiaries' => $byProgram('food')->slice(5)->all(),
            'funding' => [['donation_id' => $d['rice']->id, 'amount' => 400]], 'notes' => 'Two families absent; packs held at barangay hall.'], $maria);
        $recorder->record(['activity_id' => $a['relief1']->id, 'item_name' => 'Bottled Water', 'quantity' => 320, 'unit_id' => $unit('Box'),
            'distributed_at' => $a['relief1']->activity_date, 'beneficiaries' => $byProgram('relief')->all(),
            'funding' => [['donation_id' => $d['water']->id, 'amount' => 320]]], $maria);
        $recorder->record(['activity_id' => $a['relief1']->id, 'item_name' => 'Hygiene Kits', 'quantity' => 500, 'unit_id' => $unit('Kit'),
            'distributed_at' => $a['relief1']->activity_date, 'beneficiaries' => $byProgram('relief')->all(),
            'funding' => [['donation_id' => $d['hyg']->id, 'amount' => 450], ['donation_id' => $d['cash1']->id, 'amount' => 6000]],
            'notes' => '50 extra kits purchased locally from the cash donation.'], $maria);
        $recorder->record(['activity_id' => $a['edu1']->id, 'item_name' => 'School Supply Kits', 'quantity' => 220, 'unit_id' => $unit('Kit'),
            'distributed_at' => $a['edu1']->activity_date, 'beneficiaries' => $byProgram('education')->all(),
            'funding' => [['donation_id' => $d['supplies']->id, 'amount' => 220]]], $jose);

        // ---- Volunteers & schedules ----
        $vols = collect([
            ['Paolo', 'Reyes', 'Driving, Logistics', 'Weekends', $volUser->id],
            ['Andrea', 'Lim', 'First aid, Registration', 'Saturdays'],
            ['Mark', 'Bautista', 'Packing, Logistics', 'Weekday mornings'],
            ['Kristine', 'Navarro', 'Teaching, Reading tutor', 'Saturday mornings'],
            ['Jerome', 'Castro', 'Cooking, Food processing', 'Weekends'],
            ['Bea', 'Fernandez', 'Registration, Data entry', 'Flexible'],
            ['Nico', 'Del Rosario', 'Packing, Crowd control', 'Weekends'],
            ['Joy', 'Manalo', 'Teaching, Arts', 'Saturdays'],
        ])->map(fn ($v) => Volunteer::create(['first_name' => $v[0], 'last_name' => $v[1], 'skills' => $v[2], 'availability' => $v[3],
            'user_id' => $v[4] ?? null, 'email' => strtolower($v[0]).'.'.strtolower(str_replace(' ', '', $v[1])).'@volunteer.test',
            'phone' => '0919 '.random_int(100, 999).' '.random_int(1000, 9999), 'status' => 'active']));

        $scheduler = app(VolunteerScheduler::class);
        $plan = [
            'pack1' => [[0, 'Driver'], [2, 'Packing'], [5, 'Registration desk']],
            'relief1' => [[0, 'Logistics'], [1, 'First aid station'], [6, 'Crowd control']],
            'edu1' => [[3, 'Distribution'], [7, 'Program host']],
            'pack3' => [[0, 'Driver'], [1, 'Registration desk'], [2, 'Packing']],
            'relief2' => [[2, 'Assembly line'], [6, 'Assembly line'], [5, 'Inventory count']],
            'edu2' => [[3, 'Reading tutor'], [7, 'Reading tutor']],
            'work1' => [[4, 'Lead demonstrator']],
        ];
        foreach ($plan as $key => $rows) {
            $activity = $a[$key];
            $past = $activity->status === 'completed';
            $activity->status = 'scheduled'; // allow assignment, then restore
            foreach ($rows as [$vi, $task]) {
                $assignment = $scheduler->assign($vols[$vi], $activity, $task, $maria);
                if ($past) {
                    $assignment->update(['status' => 'completed']);
                }
            }
            if ($past) {
                $activity->update(['status' => 'completed']);
            }
        }

        // ---- Messages ----
        $msg = fn ($from, $to, $body, $minutesAgo, $activity = null, $read = true) => Message::forceCreate([
            'sender_id' => $from->id, 'recipient_id' => $to->id, 'body' => $body, 'activity_id' => $activity?->id,
            'read_at' => $read ? now()->subMinutes($minutesAgo - 1) : null, 'created_at' => now()->subMinutes($minutesAgo),
        ]);
        $msg($maria, $volUser, 'Hi Paolo! Can you drive the van for the Tumana distribution? Pick-up at the warehouse 7:30 AM.', 300, $a['pack3']);
        $msg($volUser, $maria, 'Yes po, I can drive. Should I bring the folding tables too?', 280, $a['pack3']);
        $msg($maria, $volUser, 'Yes please, 4 tables. Salamat!', 270, $a['pack3'], false);
        $msg($jose, $maria, 'Maria, can we borrow 2 of your volunteers for the reading tutorial on Saturday?', 90, $a['edu2'], false);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'system.seeded', 'description' => 'Demo data loaded']);
    }
}
