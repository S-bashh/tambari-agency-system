<?php
declare(strict_types=1);

/** Fictional sample data. Every row is tracked in demo_records so it can be removed without touching real records. */
final class Demo
{
    public static function loaded(): bool { return (int)db_val('SELECT COUNT(*) FROM demo_records') > 0; }
    public static function counts(): array { return array_column(db_all('SELECT table_name, COUNT(*) n FROM demo_records GROUP BY table_name'), 'n', 'table_name'); }

    private static function track(string $t, int $id): void { db_exec('INSERT INTO demo_records (table_name, record_id) VALUES (?,?)', [$t, $id]); }
    private static function d(string $rel): string { return date('Y-m-d', strtotime($rel)); }

    public static function load(): void
    {
        if (self::loaded()) throw new RuntimeException('Demo data is already loaded.');
        $me = (int)Auth::id();
        $n = ['c' => 0, 'p' => 0, 't' => 0, 'pay' => 0, 's' => 0, 'l' => 0];

        $person = function (string $name, string $phone, array $roles, array $x = []) use (&$n, $me): int {
            $n['c']++;
            db_run('INSERT INTO customers (ref, full_name, gender, phone, phone_norm, email, address, town, nationality, occupation, id_type, id_number, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [sprintf('DEMO-C-%03d', $n['c']), $name . ' (Demo)', $x['gender'] ?? null, $phone, norm_phone($phone), $x['email'] ?? null, $x['address'] ?? null, 'Kano', 'Nigerian', $x['job'] ?? null, 'National ID (NIN)', '000000000' . $n['c'], $me]);
            $id = db_id(); self::track('customers', $id);
            foreach ($roles as $r) {
                db_exec('INSERT INTO customer_roles (customer_id, role) VALUES (?,?)', [$id, $r]);
                if ($r === 'tenant') db_exec('INSERT INTO tenants (customer_id, number_of_kids) VALUES (?,?)', [$id, $x['kids'] ?? null]);
                if ($r === 'landlord') db_exec('INSERT INTO landlords (customer_id, bank_name, account_name, account_number) VALUES (?,?,?,?)', [$id, 'Demo Bank', $name, '0000000000']);
                if ($r === 'guarantor') db_exec('INSERT INTO guarantors (customer_id) VALUES (?)', [$id]);
            }
            return $id;
        };
        $prop = function (string $title, string $type, string $cat, string $listing, string $area, int $landlord, array $x = []) use (&$n, $me): int {
            $n['p']++;
            db_run('INSERT INTO properties (ref, title, property_type, category, listing_for, address, area, lga, state, description, rent_price, rent_period, sale_price, deposit, agency_fee, landlord_id, agent_id, status, bedrooms, bathrooms, toilets, furnishing, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [sprintf('DEMO-P-%03d', $n['p']), $title . ' (Demo)', $type, $cat, $listing, ($x['no'] ?? $n['p']) . ' Demo Street', $area, 'Kano Municipal', 'Kano',
                'Sample listing created by the demo data loader.', $x['rent'] ?? null, $x['period'] ?? 'annual', $x['sale'] ?? null, $x['deposit'] ?? null, $x['agency'] ?? null, $landlord, $me, 'available', $x['beds'] ?? null, $x['baths'] ?? null, $x['beds'] ?? null, 'unfurnished', $me]);
            $id = db_id(); self::track('properties', $id);
            db_exec('INSERT INTO property_status_log (property_id, from_status, to_status, note, changed_by) VALUES (?,NULL,?,?,?)', [$id, 'available', 'Property added', $me]);
            return $id;
        };
        $setProp = function (int $id, string $to, string $note): void { $f = db_val('SELECT status FROM properties WHERE id = ?', [$id]); Tenancy::setPropertyStatus($id, $f, $to, $note); };
        $pay = function (int $customer, ?int $tenancy, ?int $sale, ?int $property, string $type, string $amount, string $date, string $method, string $purpose) use (&$n, $me): void {
            $n['pay']++;
            $ref = $method === 'cash' ? null : 'DEMO-REF-' . str_pad((string)$n['pay'], 4, '0', STR_PAD_LEFT);
            db_run('INSERT INTO payments (txn_id, customer_id, tenancy_id, sale_id, property_id, payment_type, amount, payment_date, method, reference, purpose, received_by, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [sprintf('DEMO-TXN-%06d', $n['pay']), $customer, $tenancy, $sale, $property, $type, $amount, $date, $method, $ref, $purpose, 'Demo Cashier', $me]);
            $pid = db_id(); self::track('payments', $pid);
            db_exec('INSERT INTO receipts (receipt_no, payment_id, issued_by) VALUES (?,?,?)', [sprintf('DEMO-%s-%06d', substr($date, 0, 4), $n['pay']), $pid, $me]);
            self::track('receipts', db_id());
            if ($tenancy) Tenancy::recalc($tenancy);
            if ($sale) Sale::recalc($sale);
        };
        $tenancy = function (int $tenant, int $property, int $landlord, ?int $guarantor, string $start, int $months, string $freq, string $rent, array $fees, string $status) use (&$n, $me): int {
            $n['t']++;
            $row = ['duration_months' => $months, 'rent_frequency' => $freq, 'rent_amount' => $rent, 'security_deposit' => $fees['deposit'] ?? 0, 'agency_fee' => $fees['agency'] ?? 0, 'legal_fee' => $fees['legal'] ?? 0, 'service_charge' => 0, 'other_fees' => 0];
            $tot = Tenancy::totals($row);
            db_run('INSERT INTO tenancies (ref, tenant_id, property_id, landlord_id, guarantor_id, start_date, duration_months, end_date, rent_amount, rent_frequency, security_deposit, agency_fee, legal_fee, total_payable, status, activated_at, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [sprintf('DEMO-T-%03d', $n['t']), $tenant, $property, $landlord, $guarantor, $start, $months, Tenancy::endDate($start, $months), $rent, $freq,
                $fees['deposit'] ?? 0, $fees['agency'] ?? 0, $fees['legal'] ?? 0, $tot['total_payable'], $status, $status === 'draft' ? null : $start . ' 09:00:00', $me]);
            $id = db_id(); self::track('tenancies', $id);
            return $id;
        };
        $sale = function (string $type, int $asset, int $seller, int $buyer, string $price, string $deposit, string $status, ?string $rate, array $inst = []) use (&$n, $me): int {
            $n['s']++;
            $comm = $rate ? Money::fromKobo((int)round(Money::kobo($price) * (float)$rate / 100)) : '0.00';
            db_run('INSERT INTO sales (ref, sale_type, property_id, plot_id, seller_id, buyer_id, agent_id, sale_price, deposit, status, commission_rate, commission_amount, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [sprintf('DEMO-S-%03d', $n['s']), $type, $type === 'property' ? $asset : null, $type === 'plot' ? $asset : null, $seller, $buyer, $me, $price, $deposit, $status, $rate, $comm, $me]);
            $id = db_id(); self::track('sales', $id);
            foreach ($inst as [$dt, $am, $lb]) db_exec('INSERT INTO sale_installments (sale_id, due_date, amount, label) VALUES (?,?,?,?)', [$id, $dt, $am, $lb]);
            return $id;
        };

        db()->beginTransaction();
        try {
            // ---- people ----
            $L1 = $person('Alhaji Usman Dankade', '08000000101', ['landlord'], ['gender' => 'male', 'address' => '12 Demo Road', 'job' => 'Businessman']);
            $L2 = $person('Hajiya Rabi Sulaiman', '08000000102', ['landlord', 'seller'], ['gender' => 'female', 'address' => '4 Sample Close', 'job' => 'Trader']);
            $L3 = $person('Engr. Yakubu Maigari', '08000000103', ['landlord', 'seller'], ['gender' => 'male', 'address' => '9 Example Avenue', 'job' => 'Engineer']);
            $G1 = $person('Dr. Musa Aminu', '08000000111', ['guarantor'], ['gender' => 'male', 'job' => 'Lecturer', 'address' => '3 Faculty Quarters']);
            $G2 = $person('Mal. Danjuma Isa', '08000000112', ['guarantor'], ['gender' => 'male', 'job' => 'Civil servant']);
            $T = [];
            foreach ([['Ibrahim Garba', 'male', 'Teacher'], ['Fatima Yusuf', 'female', 'Nurse'], ['Sani Abdullahi', 'male', 'Trader'], ['Zainab Bello', 'female', 'Banker'], ['Aliyu Kabiru', 'male', 'Driver'], ['Hauwa Lawal', 'female', 'Tailor']] as $i => [$nm, $g, $job]) {
                $T[$i + 1] = $person($nm, '0800000020' . ($i + 1), ['tenant'], ['gender' => $g, 'job' => $job, 'kids' => $i % 4, 'address' => ($i + 1) . ' Tenant Lane']);
            }
            $B1 = $person('Chief Emeka Obi', '08000000301', ['buyer'], ['gender' => 'male', 'job' => 'Contractor']);
            $B2 = $person('Aisha Mohammed', '08000000302', ['buyer'], ['gender' => 'female', 'job' => 'Pharmacist']);
            $B3 = $person('Bashir Sule', '08000000303', ['buyer'], ['gender' => 'male', 'job' => 'Farmer']);
            foreach ([[1, $G1, 'Employer'], [2, $G2, 'Family friend']] as [$ti, $g, $rel]) {
                db_exec('INSERT INTO tenant_guarantors (tenant_id, guarantor_id, relationship, years_known, consented) VALUES ((SELECT id FROM tenants WHERE customer_id = ?), (SELECT id FROM guarantors WHERE customer_id = ?), ?, ?, 1)', [$T[$ti], $g, $rel, '5 years']);
            }

            // ---- rental properties and tenancies ----
            $P1 = $prop('3 Bedroom Flat, Sharada', 'flat', 'residential', 'rent', 'Sharada', $L1, ['rent' => '1200000', 'agency' => '120000', 'deposit' => '100000', 'beds' => 3, 'baths' => 2]);
            $P2 = $prop('2 Bedroom Flat, Nassarawa', 'flat', 'residential', 'rent', 'Nassarawa', $L1, ['rent' => '850000', 'agency' => '85000', 'beds' => 2, 'baths' => 2]);
            $P3 = $prop('Self-contained Room, Fagge', 'apartment', 'residential', 'rent', 'Fagge', $L2, ['rent' => '45000', 'period' => 'monthly', 'agency' => '45000', 'beds' => 1, 'baths' => 1]);
            $P4 = $prop('Shop on Zoo Road', 'shop', 'commercial', 'rent', 'Zoo Road', $L2, ['rent' => '1800000', 'agency' => '180000']);
            $P5 = $prop('4 Bedroom Duplex, Bompai', 'duplex', 'residential', 'rent', 'Bompai', $L3, ['rent' => '3500000', 'agency' => '350000', 'deposit' => '200000', 'beds' => 4, 'baths' => 4]);
            $prop('Office Space, Murtala Mohammed Way', 'office', 'commercial', 'rent', 'Murtala Mohammed Way', $L3, ['rent' => '2400000', 'agency' => '240000']);
            $P7 = $prop('1 Bedroom Flat, Gyadi-Gyadi', 'flat', 'residential', 'rent', 'Gyadi-Gyadi', $L1, ['rent' => '650000', 'beds' => 1, 'baths' => 1]);

            $tA = $tenancy($T[1], $P1, $L1, $G1, self::d('-5 months'), 12, 'annual', '1200000', ['deposit' => 100000, 'agency' => 120000], 'active');
            $pay($T[1], $tA, null, $P1, 'rent', '1200000', self::d('-5 months'), 'bank_transfer', 'Rent for 3 Bedroom Flat, Sharada');
            $pay($T[1], $tA, null, $P1, 'agency_fee', '120000', self::d('-5 months'), 'cash', 'Agency fee');
            $pay($T[1], $tA, null, $P1, 'deposit', '100000', self::d('-5 months'), 'cash', 'Caution deposit');
            $setProp($P1, 'rented', 'Demo tenancy activated');

            $startB = self::d('+7 days -1 year');
            $tB = $tenancy($T[2], $P2, $L1, $G2, $startB, 12, 'annual', '850000', ['agency' => 85000], 'active');
            $pay($T[2], $tB, null, $P2, 'rent', '850000', $startB, 'pos', 'Rent for 2 Bedroom Flat, Nassarawa');
            $pay($T[2], $tB, null, $P2, 'agency_fee', '85000', $startB, 'cash', 'Agency fee');
            $setProp($P2, 'rented', 'Demo tenancy activated');

            $startC = self::d('-75 days');
            $tC = $tenancy($T[3], $P3, $L2, null, $startC, 12, 'monthly', '45000', ['agency' => 45000], 'active');
            $pay($T[3], $tC, null, $P3, 'rent', '90000', $startC, 'cash', 'First month rent and agency fee');
            $setProp($P3, 'rented', 'Demo tenancy activated');

            $startD = self::d('-12 days +1 day -1 year');
            $tD = $tenancy($T[4], $P4, $L2, null, $startD, 12, 'annual', '1800000', ['agency' => 180000], 'active');
            $pay($T[4], $tD, null, $P4, 'rent', '1980000', $startD, 'bank_transfer', 'Rent and agency fee for Shop on Zoo Road');
            $setProp($P4, 'rented', 'Demo tenancy activated');

            $tE = $tenancy($T[5], $P5, $L3, null, self::d('+10 days'), 12, 'annual', '3500000', ['deposit' => 200000, 'agency' => 350000], 'draft');
            $pay($T[5], $tE, null, $P5, 'deposit', '500000', self::d('-2 days'), 'cash', 'Part payment towards tenancy');
            $setProp($P5, 'reserved', 'Demo draft tenancy');

            $startF = self::d('-14 months');
            $tF = $tenancy($T[6], $P7, $L1, null, $startF, 12, 'annual', '650000', [], 'terminated');
            db_exec("UPDATE tenancies SET terminated_at = ?, termination_reason = 'Tenant vacated at end of term' WHERE id = ?", [self::d('-2 months') . ' 10:00:00', $tF]);
            $pay($T[6], $tF, null, $P7, 'rent', '650000', $startF, 'cash', 'Rent for 1 Bedroom Flat, Gyadi-Gyadi');

            // ---- house sales ----
            $P8 = $prop('5 Bedroom Detached House, Kano GRA', 'house', 'residential', 'sale', 'Kano GRA', $L3, ['sale' => '85000000', 'beds' => 5, 'baths' => 5]);
            $P9 = $prop('4 Bedroom Bungalow, Gwarzo Road', 'house', 'residential', 'sale', 'Gwarzo Road', $L2, ['sale' => '32000000', 'beds' => 4, 'baths' => 3]);
            $prop('3 Bedroom Terrace, Hotoro', 'house', 'residential', 'sale', 'Hotoro', $L1, ['sale' => '18500000', 'beds' => 3, 'baths' => 2]);

            $s1 = $sale('property', $P8, $L3, $B1, '85000000', '20000000', 'reserved', '2.5');
            $setProp($P8, 'reserved', 'Demo sale');
            foreach ([['20000000', '-120 days', 'bank_transfer'], ['35000000', '-70 days', 'bank_transfer'], ['30000000', '-20 days', 'bank_transfer']] as [$a, $dt, $m]) $pay($B1, null, $s1, $P8, 'sale', $a, self::d($dt), $m, 'Payment for 5 Bedroom Detached House, Kano GRA');
            db_exec("UPDATE sales SET status = 'completed', completed_at = ?, sale_date = ? WHERE id = ?", [self::d('-18 days') . ' 12:00:00', self::d('-18 days'), $s1]);
            $setProp($P8, 'sold', 'Demo sale completed');

            $s2 = $sale('property', $P9, $L2, $B2, '32000000', '8000000', 'reserved', '2', [[self::d('-60 days'), '8000000', 'Deposit'], [self::d('-5 days'), '12000000', 'Second instalment'], [self::d('+60 days'), '12000000', 'Final instalment']]);
            $setProp($P9, 'reserved', 'Demo sale');
            $pay($B2, null, $s2, $P9, 'sale', '8000000', self::d('-60 days'), 'bank_transfer', 'Deposit for 4 Bedroom Bungalow, Gwarzo Road');
            $pay($B2, null, $s2, $P9, 'sale', '4000000', self::d('-8 days'), 'cash', 'Part of second instalment');

            // ---- land ----
            db_run("INSERT INTO land_estates (name, location, lga, state, seller_id, title_status, description, created_by) VALUES (?,?,?,?,?,?,?,?)",
                ['Demo Gardens Estate', 'Off Maiduguri Road', 'Kumbotso', 'Kano', $L2, 'Certificate of Occupancy (C of O)', 'Sample estate created by the demo data loader.', $me]);
            $est = db_id(); self::track('land_estates', $est);
            $plots = [];
            for ($i = 1; $i <= 10; $i++) {
                $n['l']++;
                db_run('INSERT INTO land_plots (ref, estate_id, plot_number, block_number, location, area, lga, state, size_text, title_status, list_price, seller_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [sprintf('DEMO-L-%03d', $n['l']), $est, (string)$i, 'A', 'Off Maiduguri Road', 'Maiduguri Road', 'Kumbotso', 'Kano', '50 x 100 ft', 'Certificate of Occupancy (C of O)', '1500000', $L2, $me]);
                $plots[$i] = db_id(); self::track('land_plots', $plots[$i]);
            }
            $s3 = $sale('plot', $plots[1], $L2, $B3, '1500000', '500000', 'reserved', '3');
            db_exec("UPDATE land_plots SET status = 'reserved' WHERE id = ?", [$plots[1]]);
            $pay($B3, null, $s3, null, 'land', '500000', self::d('-90 days'), 'cash', 'Deposit for Block A, Plot 1');
            $pay($B3, null, $s3, null, 'land', '1000000', self::d('-40 days'), 'bank_transfer', 'Balance for Block A, Plot 1');
            db_exec("UPDATE sales SET status = 'completed', completed_at = ?, sale_date = ? WHERE id = ?", [self::d('-38 days') . ' 11:00:00', self::d('-38 days'), $s3]);
            db_exec("UPDATE land_plots SET status = 'sold' WHERE id = ?", [$plots[1]]);

            $s4 = $sale('plot', $plots[2], $L2, $B1, '1500000', '500000', 'reserved', '3', [[self::d('-10 days'), '500000', 'Deposit'], [self::d('+30 days'), '500000', 'Second payment'], [self::d('+60 days'), '500000', 'Final payment']]);
            db_exec("UPDATE land_plots SET status = 'reserved' WHERE id = ?", [$plots[2]]);
            $pay($B1, null, $s4, null, 'land', '500000', self::d('-10 days'), 'pos', 'Deposit for Block A, Plot 2');

            $sale('plot', $plots[3], $L2, $B2, '1500000', '300000', 'negotiation', '3');
            $s6 = $sale('plot', $plots[4], $L2, $B3, '1500000', '300000', 'cancelled', '3');
            db_exec("UPDATE sales SET cancelled_at = ?, cancel_reason = 'Buyer changed his mind (demo)' WHERE id = ?", [self::d('-25 days') . ' 15:00:00', $s6]);

            Audit::log('demo_load', 'settings', null, 'Loaded demo data (' . array_sum(self::counts()) . ' records, all marked Demo)');
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }

    /** @return string|null error message, or null on success */
    public static function remove(): ?string
    {
        $ids = [];
        foreach (db_all('SELECT table_name, record_id FROM demo_records') as $r) $ids[$r['table_name']][] = (int)$r['record_id'];
        if (!$ids) return 'There is no demo data to remove.';
        $in = fn(string $t) => implode(',', $ids[$t] ?? [0]);
        db()->beginTransaction();
        try {
            $docFiles = [];
            foreach (['customer' => 'customers', 'property' => 'properties', 'tenancy' => 'tenancies', 'sale' => 'sales', 'plot' => 'land_plots'] as $owner => $tbl) {
                if (empty($ids[$tbl])) continue;
                foreach (db_all("SELECT id, stored_name FROM documents WHERE owner_type = ? AND owner_id IN (" . $in($tbl) . ")", [$owner]) as $d) { $docFiles[] = $d['stored_name']; db_exec('DELETE FROM documents WHERE id = ?', [(int)$d['id']]); }
            }
            if (!empty($ids['tenancies'])) db_exec('DELETE FROM agreements WHERE tenancy_id IN (' . $in('tenancies') . ')');
            foreach (['receipts', 'payments', 'sales', 'tenancies', 'land_plots', 'land_estates', 'properties', 'customers'] as $t) {
                if (!empty($ids[$t])) db_exec("DELETE FROM $t WHERE id IN (" . $in($t) . ')');
            }
            db_exec('DELETE FROM demo_records');
            Audit::log('demo_remove', 'settings', null, 'Removed demo data');
            db()->commit();
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            if (($e->errorInfo[0] ?? '') === '23000') {
                return 'Some demo records are now used by your own records (for example a real payment, tenancy or sale that involves a demo person or property). Remove or archive those first, then try again.';
            }
            throw $e;
        }
        foreach ($docFiles as $f) @unlink(TEAMS_ROOT . '/storage/documents/' . basename($f));
        return null;
    }
}
