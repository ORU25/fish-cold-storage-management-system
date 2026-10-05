<?php

namespace Database\Seeders;

use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Enums\QrLabelStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\Location;
use App\Models\OutboundOrder;
use App\Models\OutboundScan;
use App\Models\Product;
use App\Models\QrLabel;
use App\Models\QrPrintBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Demo data for trying the app: users, master data, stickers, inbound batches and outbound orders over the last 60 days.
 * Each step runs as the user who would do it and at its own date, so stock, orders and the activity log stay consistent.
 * Run on an empty database: php artisan migrate:fresh --seeder=DemoSeeder. Every account's password is "password".
 */
class DemoSeeder extends Seeder
{
    private Carbon $today;

    /**
     * Stickers not yet stuck on a box, in code order.
     *
     * @var list<QrLabel>
     */
    private array $labels = [];

    /**
     * @var array<string, Product> display name => product
     */
    private array $products = [];

    /**
     * @var array<string, Location> name => location
     */
    private array $locations = [];

    public function run(): void
    {
        $this->today = now()->startOfDay();

        try {
            $this->seed();
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    private function seed(): void
    {
        $this->at(60);
        $this->call(DatabaseSeeder::class);
        $owner = User::where('username', 'owner')->sole();

        $this->as($owner);
        $admin = $this->user('Rina', 'admin', Role::Admin);
        $andi = $this->user('Andi', 'andi', Role::Staff);
        $budi = $this->user('Budi', 'budi', Role::Staff);
        $joko = $this->user('Joko', 'joko', Role::Staff);
        $joko->update(['is_active' => false]);
        ActivityLog::recordChanges('user.updated', $joko);

        $this->at(59);
        $this->as($admin);
        foreach (['Blok A', 'Blok B', 'Blok C', 'Freezer Depan', 'Blok Lama'] as $name) {
            $this->locations[$name] = Location::create(['name' => $name]);
            ActivityLog::record('location.created', $this->locations[$name], newValues: $this->locations[$name]->only('name', 'description', 'is_active'));
        }
        $this->locations['Blok Lama']->update(['is_active' => false]);
        ActivityLog::recordChanges('location.updated', $this->locations['Blok Lama']);

        foreach ([['MB', 'A', '3-5', 540], ['MB', 'A', '6-10', 540], ['MB', 'B', '6-10', 540], ['MB', 'PP', '15-20', 540], ['Tongkol', '', '3-5', 365], ['Cakalang', 'A', '', 365], ['Layang', '', '', 180], ['Kembung', '', '', 180]] as [$fish, $grade, $size, $shelfLife]) {
            $product = Product::create(['fish_name' => $fish, 'grade' => $grade, 'size' => $size, 'shelf_life_days' => $shelfLife]);
            ActivityLog::record('product.created', $product, newValues: $product->only($product->getFillable()));
            $this->products[$product->display_name] = $product;
        }
        $this->products['Kembung']->update(['is_active' => false]);
        ActivityLog::recordChanges('product.updated', $this->products['Kembung']);

        $this->at(45);
        $this->stickers($admin, 62);
        $this->voidLastStickers(2);

        $this->at(40);
        $this->inbound($andi, 'PT Samudra Jaya', 'SJ-1041', [
            ['MB A 3-5', 'Blok A', 20, 6],
            ['MB A 6-10', 'Blok A', 120, 8],
            ['MB B 6-10', 'Blok B', 200, 6],
            ['Tongkol 3-5', 'Blok C', 90, 5],
        ]);

        $this->at(38);
        $this->as($admin);
        $this->revise($this->boxesOf('MB B 6-10')->first(), ['expired_date' => $this->today->copy()->addDays(210)->toDateString()], 'Salah baca tanggal expired di dus.');
        $this->revise($this->boxesOf('Tongkol 3-5')->first(), ['production_date' => $this->today->copy()->subDays(50)->toDateString()], 'Tanggal produksi terlewat saat scan masuk.');

        $this->at(30, 9);
        $order = $this->order($admin, 'Toko Ikan Segar Makmur', ['MB A 3-5' => 4, 'Tongkol 3-5' => 2]);
        $this->open($order);
        $this->at(30, 13);
        $this->scanOut($andi, $order, 'MB A 3-5', 4);
        $this->scanOut($andi, $order, 'Tongkol 3-5', 2);
        $this->as($admin);
        $order->update(['status' => OrderStatus::Completed]);
        ActivityLog::recordChanges('order.completed', $order);

        $this->at(20);
        $this->inbound($budi, 'CV Laut Biru', null, [
            ['MB A 3-5', 'Blok A', 150, 6],
            ['MB PP 15-20', 'Freezer Depan', 300, 5],
            ['Cakalang A', 'Blok C', 60, 6],
            ['Layang', 'Blok B', 25, 4],
        ]);

        $this->at(18, 10);
        $this->as($admin);
        foreach ($this->boxesOf('MB A 6-10')->take(3) as $box) {
            $box->moveTo($this->locations['Blok B']);
        }
        $this->boxesOf('Layang')->first()->moveTo($this->locations['Freezer Depan']);

        $this->at(15, 9);
        $order = $this->order($admin, 'Restoran Bahari', ['MB A 6-10' => 3, 'Cakalang A' => 5]);
        $this->open($order);
        $this->at(15, 13);
        $this->scanOut($budi, $order, 'MB A 6-10', 3);
        $this->scanOut($budi, $order, 'Cakalang A', 2);
        $this->as($admin);
        $this->cancel($order, 'Pembeli hanya sanggup mengambil 2 dus Cakalang, dibuat order baru.');
        $order = $this->order($admin, 'Restoran Bahari', ['MB A 6-10' => 3, 'Cakalang A' => 2]);
        $this->open($order);
        $this->at(15, 15);
        $this->scanOut($budi, $order, 'MB A 6-10', 3);
        $this->scanOut($budi, $order, 'Cakalang A', 2);
        $this->as($admin);
        $order->update(['status' => OrderStatus::Completed]);
        ActivityLog::recordChanges('order.completed', $order);

        $this->at(12);
        $this->cancel($this->order($admin, 'CV Pasar Pagi', ['Layang' => 2]));

        $this->at(10);
        $this->stickers($admin, 40);

        $this->at(8, 10);
        $this->as($admin);
        $damaged = $this->requestAdjustment($this->boxesOf('Layang')->first(), AdjustmentType::Damaged, 'Dus sobek dan ikan mencair, ditemukan saat hitung fisik.');
        $this->at(8, 16);
        $this->as($owner);
        $this->decideAdjustment($damaged, AdjustmentStatus::Approved, 'Sudah dicek langsung, dibuang.');

        $this->at(5);
        $this->inbound($andi, 'PT Samudra Jaya', 'SJ-1102', [
            ['MB A 6-10', 'Blok B', 240, 8],
            ['Tongkol 3-5', 'Blok C', 180, 6],
        ]);

        $this->at(1, 9);
        $order = $this->order($admin, 'PT Dapur Nusantara', ['MB A 3-5' => 5, 'MB B 6-10' => 3]);
        $this->open($order);
        $this->at(1, 14);
        $this->scanOut($andi, $order, 'MB A 3-5', 2);
        $this->scanOut($andi, $order, 'MB A 3-5', 1, 'Dus yang paling dekat expired tertutup tumpukan di belakang.');
        $this->at(1, 15);
        $wrongScan = $this->scanOut($andi, $order, 'MB B 6-10', 1)->sole();
        $this->as($admin);
        $this->cancelScanOut($wrongScan, 'Salah scan, dus ini disiapkan untuk order lain.');

        $this->at(2, 11);
        $this->as($admin);
        $this->requestAdjustment($this->boxesOf('MB PP 15-20')->first(), AdjustmentType::Lost, 'Tidak ditemukan di Freezer Depan saat hitung fisik.');

        $this->at(0, 8);
        $this->order($admin, 'Hotel Pantai Indah', ['Tongkol 3-5' => 4, 'MB PP 15-20' => 2]);
        $batch = $this->inbound($budi, 'UD Nelayan Sejahtera', 'SJ-0087', [['Layang', 'Blok B', 170, 3]], finished: false);
        $this->as($admin);
        $this->cancelScanIn($batch->boxes()->latest('qr_code')->first(), 'Dus milik supplier lain, ikut terbawa di truk.');
    }

    private function requestAdjustment(Box $box, AdjustmentType $type, string $reason): Adjustment
    {
        $adjustment = Adjustment::create(['box_id' => $box->id, 'type' => $type, 'reason' => $reason, 'status' => AdjustmentStatus::Pending, 'requested_by' => Auth::id()]);
        $box->update(['status' => BoxStatus::PendingAdjustment]);
        ActivityLog::record('adjustment.requested', $box, ['status' => BoxStatus::InWarehouse->value], ['status' => BoxStatus::PendingAdjustment->value, 'type' => $type->value], $reason);

        return $adjustment;
    }

    private function decideAdjustment(Adjustment $adjustment, AdjustmentStatus $decision, ?string $note = null): void
    {
        $boxStatus = $decision === AdjustmentStatus::Approved ? $adjustment->type->boxStatus() : BoxStatus::InWarehouse;
        $adjustment->update(['status' => $decision, 'decided_by' => Auth::id(), 'decided_at' => now(), 'decision_note' => $note]);
        $adjustment->box->update(['status' => $boxStatus]);
        ActivityLog::record("adjustment.{$decision->value}", $adjustment->box, ['status' => BoxStatus::PendingAdjustment->value], ['status' => $boxStatus->value, 'type' => $adjustment->type->value], $note);
    }

    /**
     * Freeze the clock at $daysAgo before today, so timestamps, sticker codes and order numbers fall on that day.
     */
    private function at(int $daysAgo, int $hour = 8): void
    {
        Carbon::setTestNow($this->today->copy()->subDays($daysAgo)->setTime($hour, 0));
    }

    /**
     * Act as $user, so ActivityLog::record() attributes the step to them.
     */
    private function as(User $user): void
    {
        Auth::setUser($user);
    }

    private function user(string $name, string $username, Role $role): User
    {
        $user = User::create(['name' => $name, 'username' => $username, 'password' => 'password', 'role' => $role]);
        ActivityLog::record('user.created', $user, newValues: $user->only('name', 'username', 'role', 'is_active'));

        return $user;
    }

    private function stickers(User $admin, int $quantity): void
    {
        $this->as($admin);
        $batch = QrPrintBatch::generate($quantity, $admin);
        ActivityLog::record('qr.generated', $batch, newValues: ['quantity' => $batch->quantity]);
        array_push($this->labels, ...$batch->labels()->orderBy('code')->get()->all());
    }

    private function voidLastStickers(int $count): void
    {
        foreach (array_splice($this->labels, -$count) as $label) {
            $label->update(['status' => QrLabelStatus::Void]);
            ActivityLog::recordChanges('qr.voided', $label, 'Stiker rusak terkena es sebelum ditempel.');
        }
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int, 3: int}>  $rows  product, location, days until expiry (from today), box count
     */
    private function inbound(User $staff, string $supplier, ?string $deliveryNote, array $rows, bool $finished = true): InboundBatch
    {
        $this->as($staff);
        $batch = InboundBatch::create(['supplier_name' => $supplier, 'delivery_note_number' => $deliveryNote, 'created_by' => $staff->id, 'started_at' => now()]);
        ActivityLog::record('inbound.started', $batch, newValues: $batch->only('supplier_name', 'delivery_note_number'));

        foreach ($rows as [$productName, $locationName, $expiresInDays, $count]) {
            foreach (range(1, $count) as $ignored) {
                $label = array_shift($this->labels);
                $label->update(['status' => QrLabelStatus::Used, 'used_at' => now()]);

                $box = Box::create([
                    'qr_label_id' => $label->id,
                    'qr_code' => $label->code,
                    'inbound_batch_id' => $batch->id,
                    'product_id' => $this->products[$productName]->id,
                    'location_id' => $this->locations[$locationName]->id,
                    'expired_date' => $this->today->copy()->addDays($expiresInDays)->toDateString(),
                    'status' => BoxStatus::InWarehouse,
                    'scanned_in_by' => $staff->id,
                    'scanned_in_at' => now(),
                ]);
                ActivityLog::record('box.scanned_in', $box, newValues: ActivityLog::valuesOf($box, ['qr_code', 'inbound_batch_id', 'product_id', 'location_id', 'production_date', 'expired_date']));
            }
        }

        if ($finished) {
            $batch->update(['finished_at' => now()]);
            ActivityLog::record('inbound.finished', $batch, newValues: ['box_count' => $batch->boxes()->count()]);
        }

        return $batch;
    }

    /**
     * Boxes of a product still in the warehouse, in code order.
     *
     * @return Collection<int, Box>
     */
    private function boxesOf(string $productName): Collection
    {
        return Box::where('product_id', $this->products[$productName]->id)->where('status', BoxStatus::InWarehouse)->orderBy('qr_code')->get();
    }

    /**
     * Same as BoxController::update(): only the changed columns are logged, with the reason.
     *
     * @param  array<string, string>  $changes
     */
    private function revise(Box $box, array $changes, string $reason): void
    {
        $box->update($changes);
        ActivityLog::recordChanges('box.updated', $box, $reason);
    }

    /**
     * Same as InboundCancellationController: the box is soft deleted and its sticker can be scanned again.
     */
    private function cancelScanIn(Box $box, string $reason): void
    {
        $oldValues = ActivityLog::valuesOf($box, ['qr_code', 'inbound_batch_id', 'product_id', 'location_id', 'production_date', 'expired_date', 'scanned_in_by', 'scanned_in_at']);
        $box->delete();
        $box->qrLabel->update(['status' => QrLabelStatus::Available, 'used_at' => null]);
        ActivityLog::record('box.inbound_cancelled', $box, oldValues: $oldValues, reason: $reason);
    }

    /**
     * Same as OutboundScanCancellationController: the box goes back, the item needs it again, the scan stays as history.
     */
    private function cancelScanOut(OutboundScan $scan, string $reason): void
    {
        $box = $scan->box;
        $orderNumber = $scan->item->order->order_number;
        $box->update(['status' => BoxStatus::InWarehouse, 'outbound_order_id' => null, 'scanned_out_by' => null, 'scanned_out_at' => null]);
        $scan->item->decrement('quantity_scanned');
        $scan->update(['cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancel_reason' => $reason]);
        ActivityLog::record('box.outbound_cancelled', $box, ['status' => BoxStatus::Outbound->value, 'order_number' => $orderNumber], ['status' => BoxStatus::InWarehouse->value], $reason);
    }

    /**
     * @param  array<string, int>  $items  product => boxes requested
     */
    private function order(User $admin, string $destination, array $items): OutboundOrder
    {
        $this->as($admin);
        $order = OutboundOrder::create([
            'order_number' => OutboundOrder::nextOrderNumber(),
            'destination' => $destination,
            'order_date' => now()->toDateString(),
            'status' => OrderStatus::Draft,
            'created_by' => $admin->id,
        ]);

        foreach ($items as $productName => $quantity) {
            $order->items()->create(['product_id' => $this->products[$productName]->id, 'quantity_requested' => $quantity]);
        }
        ActivityLog::record('order.created', $order, newValues: ActivityLog::valuesOf($order, ['order_number', 'destination', 'order_date']) + ['items' => $items]);

        return $order;
    }

    private function open(OutboundOrder $order): void
    {
        $order->update(['status' => OrderStatus::Open]);
        ActivityLog::recordChanges('order.opened', $order);
    }

    /**
     * Same as OutboundOrderController::cancel(): scanned boxes go back to the warehouse.
     */
    private function cancel(OutboundOrder $order, ?string $reason = null): void
    {
        foreach ($order->boxes()->where('status', BoxStatus::Outbound)->get() as $box) {
            $box->update(['status' => BoxStatus::InWarehouse, 'outbound_order_id' => null, 'scanned_out_by' => null, 'scanned_out_at' => null]);
            ActivityLog::record('box.outbound_cancelled', $box, ['status' => BoxStatus::Outbound->value, 'order_number' => $order->order_number], ['status' => BoxStatus::InWarehouse->value], $reason);
        }

        OutboundScan::whereIn('outbound_order_item_id', $order->items()->select('id'))->update(['cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancel_reason' => $reason]);
        $order->items()->update(['quantity_scanned' => 0]);
        $order->update(['status' => OrderStatus::Cancelled, 'cancel_reason' => $reason]);
        ActivityLog::recordChanges('order.cancelled', $order, $reason);
    }

    /**
     * Scan out $count boxes of $productName, earliest expiry first. With a $fefoReason the latest expiry is taken instead, as a recorded FEFO violation.
     */
    /**
     * @return Collection<int, OutboundScan>
     */
    private function scanOut(User $staff, OutboundOrder $order, string $productName, int $count, ?string $fefoReason = null): Collection
    {
        $scans = new Collection;
        $this->as($staff);
        $item = $order->items()->where('product_id', $this->products[$productName]->id)->sole();
        $boxes = Box::where('product_id', $item->product_id)
            ->where('status', BoxStatus::InWarehouse)
            ->orderBy('expired_date', $fefoReason ? 'desc' : 'asc')
            ->orderBy('qr_code')
            ->limit($count)
            ->get();

        foreach ($boxes as $box) {
            $box->update(['status' => BoxStatus::Outbound, 'outbound_order_id' => $order->id, 'scanned_out_by' => $staff->id, 'scanned_out_at' => now()]);
            $scans[] = OutboundScan::create(['outbound_order_item_id' => $item->id, 'box_id' => $box->id, 'scanned_by' => $staff->id, 'fefo_violation' => $fefoReason !== null, 'fefo_reason' => $fefoReason]);
            $item->increment('quantity_scanned');

            ActivityLog::record('box.scanned_out', $box, ['status' => BoxStatus::InWarehouse->value], [
                'status' => BoxStatus::Outbound->value,
                'order_number' => $order->order_number,
                'fefo_violation' => $fefoReason !== null,
            ]);

            if ($fefoReason !== null) {
                ActivityLog::record('box.fefo_override', $box, newValues: ['order_number' => $order->order_number, 'expired_date' => $box->expired_date->toDateString()], reason: $fefoReason);
            }
        }

        return $scans;
    }
}
