<?php
declare(strict_types=1);

/** Fixed option lists shared by forms, filters and validation. */
function list_property_types(): array
{
    return ['house' => 'House', 'apartment' => 'Apartment', 'duplex' => 'Duplex', 'flat' => 'Flat', 'office' => 'Office',
            'shop' => 'Shop', 'warehouse' => 'Warehouse', 'land' => 'Land', 'plot' => 'Plot', 'estate' => 'Estate', 'other' => 'Other'];
}
function list_categories(): array
{
    return ['residential' => 'Residential', 'commercial' => 'Commercial', 'industrial' => 'Industrial', 'land' => 'Land'];
}
function list_listing_for(): array
{
    return ['rent' => 'For rent', 'sale' => 'For sale', 'both' => 'Rent or sale'];
}
function list_property_statuses(): array
{
    return [
        'available' => ['Available', 'success'], 'reserved' => ['Reserved', 'warning'], 'rented' => ['Rented', 'primary'],
        'sold' => ['Sold', 'dark'], 'under_maintenance' => ['Under maintenance', 'secondary'], 'inactive' => ['Inactive', 'secondary'],
    ];
}
/** Allowed status workflow: Available -> Reserved -> Rented or Sold, with controlled ways back. */
function property_transitions(): array
{
    return [
        'available'         => ['reserved', 'rented', 'sold', 'under_maintenance', 'inactive'],
        'reserved'          => ['available', 'rented', 'sold'],
        'rented'            => ['available', 'under_maintenance'],
        'sold'              => [],
        'under_maintenance' => ['available', 'inactive'],
        'inactive'          => ['available'],
    ];
}
function status_badge(string $status): string
{
    $s = list_property_statuses()[$status] ?? [ucfirst($status), 'secondary'];
    return '<span class="badge badge-soft bg-' . e($s[1]) . '">' . e($s[0]) . '</span>';
}
function list_rent_periods(): array { return ['annual' => 'Per year', 'monthly' => 'Per month']; }
function list_furnishing(): array { return ['unfurnished' => 'Unfurnished', 'semi_furnished' => 'Semi-furnished', 'furnished' => 'Furnished']; }
function list_water(): array { return ['Borehole' => 'Borehole', 'Mains' => 'Mains supply', 'Well' => 'Well', 'Tanker' => 'Tanker / vendor', 'None' => 'None']; }
function list_electricity(): array { return ['Prepaid meter' => 'Prepaid meter', 'Postpaid meter' => 'Postpaid meter', 'Solar / generator' => 'Solar / generator', 'None' => 'None']; }
function list_genders(): array { return ['male' => 'Male', 'female' => 'Female']; }
function list_marital(): array { return ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed']; }
function list_id_types(): array
{
    return ['National ID (NIN)' => 'National ID (NIN)', "Voter's card" => "Voter's card", "Driver's licence" => "Driver's licence",
            'International passport' => 'International passport', 'Staff ID card' => 'Staff ID card', 'Other' => 'Other'];
}
function list_roles(): array
{
    return ['tenant' => ['Tenant', 'primary'], 'landlord' => ['Landlord', 'success'], 'buyer' => ['Buyer', 'info'],
            'seller' => ['Seller', 'warning'], 'guarantor' => ['Guarantor', 'secondary']];
}
function role_badges(?string $csv): string
{
    $out = '';
    foreach (array_filter(explode(',', (string)$csv)) as $r) {
        $m = list_roles()[$r] ?? [ucfirst($r), 'secondary'];
        $out .= '<span class="badge badge-soft bg-' . e($m[1]) . ' me-1">' . e($m[0]) . '</span>';
    }
    return $out ?: '<span class="text-muted small">—</span>';
}
function list_doc_categories(string $ownerType): array
{
    $common = ['Identification', 'Agreement', 'Other'];
    $map = [
        'customer' => ['Identification', 'Passport photograph', 'Proof of address', 'Employment letter', 'Guarantor form', 'Agreement', 'Other'],
        'sale' => ['Sale agreement', 'Deed of assignment', 'Receipt / evidence of payment', 'Identification', 'Power of attorney', 'Other'],
        'plot' => ['Title document', 'Survey plan', 'Allocation letter', 'Deed of assignment', "Governor's consent", 'Layout plan', 'Other'],
        'tenancy' => ['Signed agreement', 'Tenancy form', 'Payment evidence', 'Inspection report', 'Notice', 'Other'],
        'property' => ['Title document', 'Allocation document', 'Deed', 'Agreement', 'Inspection report', 'Survey plan', 'Other'],
    ];
    $c = $map[$ownerType] ?? $common;
    return array_combine($c, $c);
}
function list_customer_contexts(): array
{
    return ['' => 'General', 'tenant' => 'Tenant', 'landlord' => 'Landlord', 'guarantor' => 'Guarantor', 'buyer' => 'Buyer', 'seller' => 'Seller'];
}
function list_nigerian_states(): array
{
    $s = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT Abuja',
          'Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau',
          'Rivers','Sokoto','Taraba','Yobe','Zamfara'];
    return array_combine($s, $s);
}

function list_title_status(): array
{
    $t = ['Certificate of Occupancy (C of O)', 'Right of Occupancy', "Governor's Consent", 'Deed of Assignment', 'Survey plan only', 'Allocation letter', 'Receipt only', 'Not stated'];
    return array_combine($t, $t);
}
function list_plot_statuses(): array
{
    return ['available' => ['Available', 'success'], 'reserved' => ['Reserved', 'warning'], 'sold' => ['Sold', 'dark'], 'inactive' => ['Inactive', 'secondary']];
}
function plot_badge(string $s): string
{
    $m = list_plot_statuses()[$s] ?? [ucfirst($s), 'secondary'];
    return '<span class="badge badge-soft bg-' . $m[1] . '">' . e($m[0]) . '</span>';
}
