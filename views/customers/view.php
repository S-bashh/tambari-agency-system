<?php
$archived = !empty($c['deleted_at']);
$kv = function (string $label, ?string $val, bool $raw = false) { if ($val === null || $val === '') return; echo '<dt>' . e($label) . '</dt><dd>' . ($raw ? $val : nl2br(e($val))) . '</dd>'; };
$age = null; if ($c['date_of_birth']) { try { $age = (new DateTime($c['date_of_birth']))->diff(new DateTime())->y; } catch (Throwable) {} }
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <span class="avatar avatar-lg"><?= e(initials($c['full_name'])) ?></span>
    <div><h1 class="mb-1"><?= e($c['full_name']) ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center"><span class="text-muted small"><?= e($c['ref']) ?></span><?= role_badges(implode(',', $roles)) ?>
      <?php if ($archived): ?><span class="badge badge-soft bg-danger">Archived</span><?php endif; ?></div></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($archived && can('customers.delete')): ?>
      <form method="post" action="<?= e(url('customers/restore')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></form>
    <?php elseif (!$archived): ?>
      <?php if (can('customers.edit')): ?><a class="btn btn-primary" href="<?= e(url('customers/edit', ['id' => $c['id']])) ?>"><i class="bi bi-pencil me-1"></i>Edit</a><?php endif; ?>
      <?php if (can('customers.delete')): ?>
      <form method="post" action="<?= e(url('customers/archive')) ?>" data-confirm="Archive <?= e($c['full_name']) ?>? They will be hidden from lists and search but all history is kept." data-confirm-title="Archive person" data-confirm-button="Archive">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-light border text-danger"><i class="bi bi-archive me-1"></i>Archive</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">Personal details</div><div class="card-body"><dl class="kv">
      <?php $kv('Phone', $c['phone']); $kv('Alt. phone', $c['phone_alt']); $kv('Email', $c['email']); $kv('Address', trim(($c['address'] ?? '') . ($c['town'] ? "\n" . $c['town'] : '')));
      $kv('Gender', $c['gender'] ? ucfirst($c['gender']) : null); $kv('Date of birth', $c['date_of_birth'] ? fmt_date($c['date_of_birth']) . ($age !== null ? " ($age yrs)" : '') : null);
      $kv('Marital status', $c['marital_status'] ? ucfirst($c['marital_status']) : null); $kv('Nationality', $c['nationality']); $kv('State of origin', $c['state_of_origin']); $kv('LGA', $c['lga']); $kv('Religion', $c['religion']); ?>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">Work &amp; identification</div><div class="card-body"><dl class="kv">
      <?php $kv('Occupation', $c['occupation']); $kv('Employer', $c['employer_name']); $kv('Employer address', $c['employer_address']); $kv('ID', trim(($c['id_type'] ?? '') . ' ' . ($c['id_number'] ?? ''))); ?>
      <?php if (!$c['occupation'] && !$c['employer_name'] && !$c['id_number']): ?><dd class="text-muted">Nothing recorded.</dd><?php endif; ?>
    </dl></div></div>
    <?php if ($c['emergency_name'] || $c['emergency_phone']): ?>
    <div class="card mb-3"><div class="card-header">Emergency contact</div><div class="card-body"><dl class="kv">
      <?php $kv('Name', $c['emergency_name']); $kv('Phone', $c['emergency_phone']); $kv('Relationship', $c['emergency_relationship']); ?></dl></div></div>
    <?php endif; ?>
    <?php if ($c['notes']): ?><div class="card mb-3"><div class="card-header">Notes</div><div class="card-body"><?= nl2br(e($c['notes'])) ?></div></div><?php endif; ?>
  </div>

  <div class="col-lg-7">
    <?php if ($tenant): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-person-badge me-2"></i>Tenant information</span></div>
      <div class="card-body pb-2"><dl class="kv">
        <?php $kv('Spouse', $tenant['spouse_name']); $kv('Spouse employer', $tenant['spouse_employer']); $kv('Office phone', $tenant['office_phone']);
        $kv('Children', $tenant['number_of_kids'] !== null ? (string)$tenant['number_of_kids'] : null); $kv('Wedding anniv.', $tenant['wedding_anniversary'] ? fmt_date($tenant['wedding_anniversary']) : null); $kv('Notes', $tenant['notes']); ?>
      </dl>
      <div class="section-title mt-3">Guarantors</div>
      <?php if (!$guarantors): ?><div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>No guarantor on file for this tenant.</div><?php endif; ?>
      <?php foreach ($guarantors as $g): ?>
        <div class="d-flex align-items-center gap-2 border rounded-3 p-2 mb-2">
          <span class="avatar"><?= e(initials($g['full_name'])) ?></span>
          <div class="flex-grow-1"><a class="fw-semibold text-decoration-none" href="<?= e(url('customers/view', ['id' => $g['cid']])) ?>"><?= e($g['full_name']) ?></a>
            <div class="small text-muted"><?= e(implode(' · ', array_filter([$g['relationship'], $g['years_known'] ? 'known ' . $g['years_known'] : null, $g['phone'], $g['consented'] ? 'consent given' : 'consent not recorded']))) ?></div></div>
          <?php if (can('tenants.manage')): ?><form method="post" action="<?= e(url('customers/unlink-guarantor')) ?>" data-confirm="Remove <?= e($g['full_name']) ?> as a guarantor for this tenant? The guarantor's own record is kept." data-confirm-title="Remove guarantor" data-confirm-button="Remove">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Remove"><i class="bi bi-x-lg"></i></button></form><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (can('tenants.manage') && can('guarantors.manage') && !$archived): ?>
        <div class="mt-3 pt-3 border-top">
          <form method="post" action="<?= e(url('customers/link-guarantor')) ?>" class="row g-2 needs-validation" novalidate>
            <?= csrf_field() ?><input type="hidden" name="tenant_customer_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="guarantor_customer_id" id="gc_id">
            <div class="col-12"><label class="form-label">Link an existing person as guarantor</label>
              <input class="form-control" data-lookup="<?= e(url('customers/lookup')) ?>" data-target="#gc_id" placeholder="Search by name or phone…" required autocomplete="off"></div>
            <div class="col-md-5"><input class="form-control" name="relationship" placeholder="Relationship (e.g. Employer)" maxlength="80"></div>
            <div class="col-md-4"><input class="form-control" name="years_known" placeholder="Known for (e.g. 5 years)" maxlength="40"></div>
            <div class="col-md-3 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="consented" value="1" id="gconsent"><label class="form-check-label small" for="gconsent">Consented</label></div></div>
            <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary btn-sm">Link guarantor</button>
              <a class="btn btn-light btn-sm border" href="<?= e(url('customers/create', ['role' => 'guarantor', 'for_tenant' => $c['id']])) ?>"><i class="bi bi-person-plus me-1"></i>Add a new person as guarantor</a></div>
          </form>
        </div>
      <?php endif; ?>
      </div></div>
    <?php endif; ?>

    <?php if ($landlord): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-person-vcard me-2"></i>Landlord</span><span class="badge badge-soft bg-success"><?= count($properties) ?> properties</span></div>
      <div class="card-body"><dl class="kv"><?php $kv('Bank', $landlord['bank_name']); $kv('Account name', $landlord['account_name']); $kv('Account no.', $landlord['account_number']); $kv('Notes', $landlord['notes']); ?></dl></div>
      <?php if ($properties): ?><ul class="list-group list-group-flush border-top">
        <?php foreach ($properties as $p): ?><li class="list-group-item d-flex justify-content-between align-items-center"><a class="text-decoration-none" href="<?= e(url('properties/view', ['id' => $p['id']])) ?>"><span class="fw-semibold"><?= e($p['title']) ?></span><span class="d-block small text-muted"><?= e($p['ref']) ?> · <?= e($p['area']) ?></span></a><?= status_badge($p['status']) ?></li><?php endforeach; ?></ul>
      <?php elseif (can('properties.create')): ?><div class="card-body border-top small text-muted">No properties yet. <a href="<?= e(url('properties/create', ['landlord' => $c['id']])) ?>">Add one for this landlord</a>.</div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($guarantor): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-shield-check me-2"></i>Guarantor</span><span class="badge badge-soft bg-secondary"><?= count($guaranteed) ?> tenant<?= count($guaranteed) === 1 ? '' : 's' ?></span></div>
      <div class="card-body"><?php if ($guarantor['notes']): ?><p><?= nl2br(e($guarantor['notes'])) ?></p><?php endif; ?>
      <?php if (!$guaranteed): ?><span class="text-muted small">Not yet linked to any tenant.</span><?php endif; ?>
      <?php foreach ($guaranteed as $g): ?><div class="d-flex align-items-center gap-2 mb-2"><span class="avatar"><?= e(initials($g['full_name'])) ?></span><div><a class="fw-semibold text-decoration-none" href="<?= e(url('customers/view', ['id' => $g['cid']])) ?>"><?= e($g['full_name']) ?></a><div class="small text-muted"><?= e(implode(' · ', array_filter([$g['relationship'], $g['phone']]))) ?></div></div></div><?php endforeach; ?>
      </div></div>
    <?php endif; ?>

    <?php if ($tenancies): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-house-check me-2"></i>Tenancies</span><?php if (can('tenancies.create') && !$archived): ?><a class="btn btn-sm btn-primary" href="<?= e(url('tenancies/create', ['tenant' => $c['id']])) ?>">New tenancy</a><?php endif; ?></div>
      <ul class="list-group list-group-flush"><?php foreach ($tenancies as $tn): $o = Tenancy::outstanding($tn); ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2">
        <a class="text-decoration-none" href="<?= e(url('tenancies/view', ['id' => $tn['id']])) ?>"><span class="fw-semibold"><?= e($tn['property_title']) ?></span><span class="d-block small text-muted"><?= e($tn['ref']) ?> · <?= e(fmt_date($tn['start_date'])) ?> to <?= e(fmt_date($tn['end_date'])) ?> · <?= Money::kobo($o) > 0 ? 'owes ' . e(money($o)) : 'settled' ?></span></a><?= Tenancy::badge($tn['eff']) ?></li><?php endforeach; ?></ul></div>
    <?php elseif ($tenant && can('tenancies.create') && !$archived): ?>
    <div class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center"><span class="text-muted small">No tenancy yet for this tenant.</span><a class="btn btn-sm btn-primary" href="<?= e(url('tenancies/create', ['tenant' => $c['id']])) ?>">Create tenancy</a></div></div>
    <?php endif; ?>
    <?php if ($sales): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-tag me-2"></i>Sales</span></div>
      <ul class="list-group list-group-flush"><?php foreach ($sales as $sl): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-2"><a class="text-decoration-none" href="<?= e(url('sales/view', ['id' => $sl['id']])) ?>"><span class="fw-semibold"><?= e($sl['asset']) ?></span><span class="d-block small text-muted"><?= e($sl['ref']) ?> · <?= (int)$sl['buyer_id'] === (int)$c['id'] ? 'as buyer' : 'as seller' ?> · <?= e(money($sl['sale_price'])) ?> · paid <?= e(money($sl['amount_paid'])) ?></span></a><?= Sale::badge($sl['status']) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($payments): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-cash-coin me-2"></i>Payment history</span><a class="small fw-normal" href="<?= e(url('payments', ['q' => $c['full_name']])) ?>">View all</a></div>
      <div class="table-responsive"><table class="table table-stack align-middle mb-0"><tbody><?php foreach ($payments as $pm): ?><tr class="<?= $pm['status'] === 'void' ? 'opacity-50' : '' ?>">
        <td class="cell-main"><?= e(fmt_date($pm['payment_date'])) ?></td><td data-label="Receipt"><a href="<?= e(url('payments/view', ['id' => $pm['id']])) ?>"><?= e($pm['receipt_no'] ?: $pm['txn_id']) ?></a><?= $pm['status'] === 'void' ? ' <span class="badge badge-soft bg-danger">Void</span>' : '' ?></td>
        <td data-label="For"><?= e(PaymentsController::types()[$pm['payment_type']] ?? $pm['payment_type']) ?></td><td data-label="Amount" class="text-end"><?= e(money($pm['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php endif; ?>
    <?php if ($landlordPayments): ?>
    <div class="card mb-3"><div class="card-header"><span><i class="bi bi-bank me-2"></i>Recent payments on this landlord's properties</span></div>
      <div class="table-responsive"><table class="table table-stack align-middle mb-0"><tbody><?php foreach ($landlordPayments as $pm): ?><tr class="<?= $pm['status'] === 'void' ? 'opacity-50' : '' ?>">
        <td class="cell-main"><?= e(fmt_date($pm['payment_date'])) ?></td><td data-label="Receipt"><a href="<?= e(url('payments/view', ['id' => $pm['id']])) ?>"><?= e($pm['receipt_no'] ?: $pm['txn_id']) ?></a></td>
        <td data-label="Payer"><?= e($pm['payer']) ?></td><td data-label="Amount" class="text-end"><?= e(money($pm['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php endif; ?>

    <?php if (can('documents.view')): ?>
      <div class="mb-3"><?php View::partial('documents', ['ownerType' => 'customer', 'ownerId' => (int)$c['id'], 'docs' => $docs, 'canUpload' => can('documents.upload') && !$archived, 'canDelete' => can('documents.delete'), 'withContext' => true]); ?></div>
    <?php endif; ?>

    <?php if (can('audit.view') && $activity): ?>
    <div class="card"><div class="card-header">Recent activity on this record</div><div class="card-body py-2"><ul class="timeline">
      <?php foreach ($activity as $a): ?><li><span class="dot"><i class="bi bi-clock-history"></i></span><div><div class="small"><?= e($a['description']) ?></div><div class="text-muted" style="font-size:.72rem"><?= e($a['username']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></div></div></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>
  </div>
</div>
