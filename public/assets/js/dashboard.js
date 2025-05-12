document.addEventListener('DOMContentLoaded', function() {
    // Handle adding new payment
    const savePaymentBtn = document.getElementById('savePayment');
    if (savePaymentBtn) {
        savePaymentBtn.addEventListener('click', async function() {
            const name = document.getElementById('paymentName').value;
            const amount = document.getElementById('paymentAmount').value;
            const dueDay = document.getElementById('dueDay').value;

            try {
                const response = await fetch('/api/payments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ name, amount, due_day: dueDay })
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    const error = await response.json();
                    alert(error.message || 'Failed to add payment');
                }
            } catch (error) {
                alert('An error occurred while adding the payment');
            }
        });
    }

    // Handle editing payment
    document.querySelectorAll('.edit-payment').forEach(button => {
        button.addEventListener('click', async function() {
            const paymentId = this.dataset.id;
            try {
                const response = await fetch(`/api/payments/${paymentId}`);
                if (response.ok) {
                    const payment = await response.json();
                    // Populate and show edit modal
                    document.getElementById('editPaymentName').value = payment.name;
                    document.getElementById('editPaymentAmount').value = payment.amount;
                    document.getElementById('editDueDay').value = payment.due_day;
                    document.getElementById('editPaymentId').value = payment.id;
                    new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
                }
            } catch (error) {
                alert('Failed to load payment details');
            }
        });
    });

    // Handle deleting payment
    document.querySelectorAll('.delete-payment').forEach(button => {
        button.addEventListener('click', async function() {
            if (confirm('Are you sure you want to delete this payment?')) {
                const paymentId = this.dataset.paymentId;
                const formData = new FormData();
                formData.append('payment_id', paymentId);
                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

                try {
                    const response = await fetch('/api/payments/delete.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        window.location.reload();
                    } else {
                        const error = await response.text();
                        alert(error || 'Failed to delete payment');
                    }
                } catch (error) {
                    alert('An error occurred while deleting the payment');
                }
            }
        });
    });

    // Handle updating balance
    const updateBalanceBtn = document.getElementById('updateBalance');
    if (updateBalanceBtn) {
        updateBalanceBtn.addEventListener('click', async function() {
            const newBalance = document.getElementById('currentBalance').value;
            try {
                const response = await fetch('/api/settings/balance', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ balance: newBalance })
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    const error = await response.json();
                    alert(error.message || 'Failed to update balance');
                }
            } catch (error) {
                alert('An error occurred while updating balance');
            }
        });
    }
}); 