<?php
session_start();
include "db.php";

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Fetch client info
$clientQuery = $conn->prepare("SELECT client_id, firstName, lastName FROM client_information WHERE user_id = ? LIMIT 1");
$clientQuery->bind_param("i", $user_id);
$clientQuery->execute();
$client = $clientQuery->get_result()->fetch_assoc();
$clientQuery->close();

if (!$client) {
    die("No client record found for this user.");
}

$client_id = (int)$client['client_id'];
$fullName  = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));

// Payment List
$invoiceQuery = $conn->prepare("
    SELECT service_id, client_id, appointment_id, vehicle_id, serviceCategory, totalCost, status, serviceDate
    FROM services
    WHERE client_id = ?
    ORDER BY serviceDate DESC, service_id DESC
");
$invoiceQuery->bind_param("i", $client_id);
$invoiceQuery->execute();
$invoices = $invoiceQuery->get_result();
$invoiceQuery->close();

// Payment History (show invoice ref + gcash ref)
$paidQuery = $conn->prepare("
    SELECT
        s.service_id, s.client_id, s.appointment_id, s.vehicle_id,
        s.serviceCategory, s.totalCost,
        p.paymentDate, p.referenceNumber, p.gcashReferenceNumber, p.paymentMethod
    FROM services s
    LEFT JOIN payments p ON s.service_id = p.service_id
    WHERE s.client_id = ? AND s.status = 'Paid'
    ORDER BY s.serviceDate DESC, s.service_id DESC
");
$paidQuery->bind_param("i", $client_id);
$paidQuery->execute();
$paidInvoices = $paidQuery->get_result();
$paidQuery->close();

/* Badge helper */
function payBadgeClass($status) {
    $s = strtolower(trim((string)$status));
    return match ($s) {
        'paid' => 'st-paid',
        'unpaid' => 'st-unpaid',
        'ready' => 'st-ready',
        default => 'st-default'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment | Rapid Repair</title>

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="payment.css">

    <style>
        /* Modal base */
        .modal{
            position: fixed; inset: 0;
            background: rgba(0,0,0,.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            z-index: 9999;
        }
        .modal.show{ display:flex; }
        .modal-content{
            width: min(780px, 100%);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 14px 34px rgba(0,0,0,.18);
            overflow: hidden;
        }
        .modal-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(15,23,42,.10);
        }
        .modal-head h2{ margin:0; font-size:18px; }
        .modal-head .close{
            width:36px;height:36px;border:none;border-radius:10px;
            background: rgba(15,23,42,.06);
            cursor:pointer;font-size:22px;line-height:1;
        }
        #paymentForm{ padding: 16px 18px 18px; }
        .form-grid{
            display:grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .form-grid label{
            display:block;
            font-weight:600;
            margin-bottom:6px;
            font-size:13px;
            color:#0f172a;
        }
        .form-grid input,
        .form-grid select,
        .form-grid textarea{
            width:100%;
            padding:10px 12px;
            border:1px solid rgba(15,23,42,.15);
            border-radius:12px;
            outline:none;
            font-size:14px;
        }
        .form-grid textarea{ min-height:86px; resize: vertical; }
        .modal-actions{
            display:flex;
            justify-content:flex-end;
            gap:10px;
            margin-top:14px;
        }
        .muted{ color:#64748b; font-size:12px; }
        @media (max-width:680px){ .form-grid{ grid-template-columns: 1fr; } }
    </style>
</head>

<body>

<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>

    <div class="search-box">
        <input type="text" id="globalSearch" placeholder="Search payments..." autocomplete="off">
        <div id="searchResults" class="search-results"></div>
    </div>

    <div class="user-info">
        <img src="pictures/user.png" alt="User">
        <div>
            <strong>Welcome!</strong><br>
            <span><?= htmlspecialchars($fullName) ?></span>
        </div>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <ul>
            <li><a href="user_home.php">Home</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="vehicle.php">Vehicle</a></li>
            <li><a href="clientreq.php">Booking</a></li>
            <li class="active"><a href="payments.php">Payment</a></li>
        </ul>
        <div class="logout"><a href="logout.php">Logout</a></div>
    </aside>

    <main class="content">

        <div class="page-head">
            <div>
                <h1 class="page-title">Payments</h1>
                <p class="page-sub">View your invoices and payment history.</p>
            </div>
        </div>

        <!-- PAYMENT LIST -->
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Payment List</h2>
                    <small>Invoices for your services</small>
                </div>
            </div>

            <div class="table-wrap">
                <table id="tblInvoices">
                    <thead>
                        <tr>
                            <th>Invoice Ref</th>
                            <th>Appointment</th>
                            <th>Vehicle</th>
                            <th>Service</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th style="width:140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invoices && $invoices->num_rows > 0): ?>
                            <?php while ($inv = $invoices->fetch_assoc()): ?>
                                <?php
                                    $serviceId = (int)$inv['service_id'];
                                    $ref = "RR-" . str_pad($serviceId, 5, '0', STR_PAD_LEFT);
                                    $status = (string)($inv['status'] ?? '');
                                    $isPaid = (strtolower(trim($status)) === 'paid');
                                    $total = (float)($inv['totalCost'] ?? 0);
                                ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($ref) ?></td>
                                    <td>#<?= (int)$inv['appointment_id'] ?></td>
                                    <td>#<?= (int)$inv['vehicle_id'] ?></td>
                                    <td><?= htmlspecialchars($inv['serviceCategory'] ?? '') ?></td>
                                    <td class="money">₱<?= number_format($total, 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= payBadgeClass($status) ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$isPaid): ?>
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-pay"
                                                data-service-id="<?= $serviceId ?>"
                                                data-vehicle-id="<?= (int)$inv['vehicle_id'] ?>"
                                                data-appointment-id="<?= (int)$inv['appointment_id'] ?>"
                                                data-ref="<?= htmlspecialchars($ref) ?>"
                                                data-total="<?= htmlspecialchars($total) ?>"
                                            >
                                                Pay
                                            </button>
                                        <?php else: ?>
                                            <span class="muted">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="empty">No invoices found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- PAYMENT HISTORY -->
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Payment History</h2>
                    <small>Paid invoices only</small>
                </div>
            </div>

            <div class="table-wrap">
                <table id="tblHistory">
                    <thead>
                        <tr>
                            <th>Invoice Ref</th>
                            <th>Service</th>
                            <th>Total Paid</th>
                            <th>Method</th>
                            <th>GCash Ref</th>
                            <th>Payment Date</th>
                            <th style="width:160px;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($paidInvoices && $paidInvoices->num_rows > 0): ?>
                            <?php while ($paid = $paidInvoices->fetch_assoc()): ?>
                                <?php
                                    $invoiceRef = $paid['referenceNumber'] ?: ("RR-" . str_pad((int)$paid['service_id'], 5, '0', STR_PAD_LEFT));
                                    $gcashRef = $paid['gcashReferenceNumber'] ?? '';
                                    $pdate = $paid['paymentDate'] ? date("Y-m-d", strtotime($paid['paymentDate'])) : "";
                                    $method = $paid['paymentMethod'] ?? '';
                                ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($invoiceRef) ?></td>
                                    <td><?= htmlspecialchars($paid['serviceCategory'] ?? '') ?></td>
                                    <td class="money">₱<?= number_format((float)$paid['totalCost'], 2) ?></td>
                                    <td><?= htmlspecialchars($method) ?></td>
                                    <td class="mono"><?= htmlspecialchars($gcashRef) ?></td>
                                    <td><?= htmlspecialchars($pdate) ?></td>
                                    <td>
                                        <a class="btn btn-ghost"
                                           href="receipt.php?service_id=<?= (int)$paid['service_id'] ?>"
                                           target="_blank" rel="noopener">
                                            View Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="empty">No payments found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<!-- PAYMENT MODAL -->
<div id="paymentModal" class="modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="payTitle">
    <div class="modal-head">
      <h2 id="payTitle">Record Payment</h2>
      <button class="close" type="button" id="paymentClose">&times;</button>
    </div>

    <form id="paymentForm">
      <input type="hidden" name="service_id" id="payment_service_id">

      <div class="form-grid">
        <div>
          <label>Vehicle ID</label>
          <input type="text" id="payment_vehicle_id" readonly>
        </div>

        <div>
          <label>Total Amount</label>
          <input type="number" id="payment_total" readonly>
        </div>

        <div>
          <label>Amount Paid</label>
          <input type="number" name="amountPaid" id="amountPaid" step="0.01" min="0" required>
        </div>

        <div>
          <label>Balance</label>
          <input type="number" id="balance" readonly>
        </div>

        <div>
          <label>Payment Method</label>
          <select name="paymentMethod" id="paymentMethod" required>
            <option value="Cash">Cash</option>
            <option value="GCash">GCash</option>
          </select>
        </div>

        <div>
          <label>Remarks</label>
          <textarea name="remarks" id="remarks"></textarea>
        </div>

        <div>
          <label>GCash Reference Number</label>
          <input
            type="text"
            name="gcashReferenceNumber"
            id="gcashReference"
            placeholder="Enter GCash reference #"
            disabled
          >
          <small class="muted">Required only for GCash.</small>
        </div>

        <div>
          <label>Invoice Reference Number</label>
          <input type="text" id="invoiceReference" readonly>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" id="paymentCancel">Cancel</button>
        <button type="submit" class="btn btn-primary" id="paymentSubmit">Save Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- Search filter BOTH tables -->
<script>
(function () {
  const search = document.getElementById("globalSearch");
  const tables = [document.getElementById("tblInvoices"), document.getElementById("tblHistory")].filter(Boolean);
  if (!search || tables.length === 0) return;

  function filterTable(table, q){
    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll("tr"));
    let visible = 0;

    rows.forEach(tr => {
      const tds = tr.querySelectorAll("td");
      if (tds.length <= 1) { tr.style.display = q ? "none" : ""; return; }
      const rowText = Array.from(tds).map(td => td.textContent).join(" ").toLowerCase();
      const match = q === "" || rowText.includes(q);
      tr.style.display = match ? "" : "none";
      if (match) visible++;
    });

    let noRow = tbody.querySelector("tr.__noresults");
    if (q !== "" && visible === 0) {
      if (!noRow) {
        noRow = document.createElement("tr");
        noRow.className = "__noresults";
        const colCount = table.querySelectorAll("thead th").length || 7;
        noRow.innerHTML = `<td colspan="${colCount}" style="text-align:center;color:#777;padding:16px;">No matching results.</td>`;
        tbody.appendChild(noRow);
      }
      noRow.style.display = "";
    } else if (noRow) {
      noRow.style.display = "none";
    }
  }

  function applyFilter(){
    const q = search.value.toLowerCase().trim();
    tables.forEach(t => filterTable(t, q));
  }

  search.addEventListener("input", applyFilter);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && document.activeElement === search) {
      search.value = "";
      applyFilter();
    }
  });
})();
</script>

<!-- Modal logic + submit -->
<script>
(function () {
  const modal = document.getElementById("paymentModal");
  const closeBtn = document.getElementById("paymentClose");
  const cancelBtn = document.getElementById("paymentCancel");
  const form = document.getElementById("paymentForm");

  const serviceIdEl = document.getElementById("payment_service_id");
  const vehicleIdEl = document.getElementById("payment_vehicle_id");
  const totalEl = document.getElementById("payment_total");
  const amountPaidEl = document.getElementById("amountPaid");
  const balanceEl = document.getElementById("balance");

  const methodEl = document.getElementById("paymentMethod");
  const gcashRefEl = document.getElementById("gcashReference");
  const invoiceRefEl = document.getElementById("invoiceReference");
  const remarksEl = document.getElementById("remarks");

  const submitBtn = document.getElementById("paymentSubmit");

  if (!modal || !form) return;

  function openModal() {
    modal.classList.add("show");
    modal.setAttribute("aria-hidden","false");
    setTimeout(() => amountPaidEl.focus(), 80);
  }

  function closeModal() {
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden","true");
    form.reset();
    gcashRefEl.disabled = true;
    gcashRefEl.required = false;
    gcashRefEl.value = "";
    submitBtn.disabled = false;
    submitBtn.textContent = "Save Payment";
  }

  function updateBalance() {
    const total = Number(totalEl.value || 0);
    const paid = Number(amountPaidEl.value || 0);
    const bal = total - paid;
    balanceEl.value = (isFinite(bal) ? bal : total).toFixed(2);
  }

  // Open from Pay button
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-pay");
    if (!btn) return;

    const serviceId = btn.dataset.serviceId || "";
    const vehicleId = btn.dataset.vehicleId || "";
    const total = Number(btn.dataset.total || 0);
    const invoiceRef = btn.dataset.ref || "";

    serviceIdEl.value = serviceId;
    vehicleIdEl.value = vehicleId;

    totalEl.value = total.toFixed(2);
    amountPaidEl.value = ""; // user types
    balanceEl.value = total.toFixed(2);

    invoiceRefEl.value = invoiceRef;

    methodEl.value = "Cash";
    gcashRefEl.value = "";
    gcashRefEl.disabled = true;
    gcashRefEl.required = false;

    remarksEl.value = "";

    openModal();
  });

  // Enable GCash ref only for GCash
  methodEl.addEventListener("change", () => {
    if (methodEl.value === "GCash") {
      gcashRefEl.disabled = false;
      gcashRefEl.required = true;
      setTimeout(() => gcashRefEl.focus(), 60);
    } else {
      gcashRefEl.value = "";
      gcashRefEl.disabled = true;
      gcashRefEl.required = false;
    }
  });

  amountPaidEl.addEventListener("input", updateBalance);

  closeBtn.addEventListener("click", closeModal);
  cancelBtn.addEventListener("click", closeModal);
  modal.addEventListener("click", (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && modal.classList.contains("show")) closeModal(); });

  // Submit -> process_payment_client.php
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const service_id = serviceIdEl.value;
    const paymentMethod = methodEl.value;
    const amountPaid = (amountPaidEl.value || "").trim();
    const remarks = (remarksEl.value || "").trim();
    const gcashReferenceNumber = (gcashRefEl.value || "").trim();

    const total = Number(totalEl.value || 0);
    const paid = Number(amountPaid || 0);

    if (!service_id) return alert("Missing service id.");
    if (amountPaid === "" || !isFinite(paid)) return alert("Please enter a valid Amount Paid.");
    if (paid < 0) return alert("Amount Paid cannot be negative.");
    if (paid > total) return alert("Amount Paid cannot exceed Total Amount.");

    if (paymentMethod === "GCash" && gcashReferenceNumber === "") {
      return alert("GCash reference number is required.");
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Processing...";

    try {
      const res = await fetch("process_payment_client.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          service_id,
          amountPaid: paid.toFixed(2),
          paymentMethod,
          gcashReferenceNumber,
          remarks
        })
      });

      const data = await res.json();

      if (!data.ok) {
        alert(data.message || "Payment failed.");
        submitBtn.disabled = false;
        submitBtn.textContent = "Save Payment";
        return;
      }

      alert(data.message || "Payment recorded successfully!");
      closeModal();
      window.location.reload();

    } catch (err) {
      console.error(err);
      alert("Network error. Please try again.");
      submitBtn.disabled = false;
      submitBtn.textContent = "Save Payment";
    }
  });

})();
</script>

</body>
</html>
