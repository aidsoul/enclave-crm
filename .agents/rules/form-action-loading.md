---
trigger: always_on
description: Mandatory requirement for loading feedback and disabled states on all form submissions and action buttons (creation, editing, status change, deletion)
---

# Form & Action Loading States Rule

Every asynchronous action in the UI that performs mutations (creation, editing, status updates, deletion, bulk actions, or submissions) MUST provide clear and immediate loading feedback:

1. **Button Disabled State**:
   - The trigger/submit button MUST be disabled while the asynchronous request is pending (e.g. `:disabled="submitting"`, `:disabled="loading"`, `:disabled="saving"`).
   - This prevents accidental double submissions and network request flooding.

2. **Visual Progress Feedback**:
   - Button text must dynamically change to indicate progress (e.g. `Создать` -> `Создание...`, `Сохранить` -> `Сохранение...`, `Удалить` -> `Удаление...`, `Принять` -> `Принятие...`, `Отменить` -> `Отмена...`).
   - For primary or standalone action buttons, include a loading spinner (`animate-spin` SVG) alongside or in place of static icons.

3. **Confirmation Modals**:
   - For confirmation modals (e.g. deleting records, cancelling orders, completing deliveries, revocations), **never** dismiss the modal before the network request finishes.
   - Keep the modal visible with the confirm button in its loading/disabled state (`Удаление...`, `disabled`) and dismiss the modal only upon successful response.
   - If an error occurs, the modal should remain open or display the error message, resetting button states so the user can retry or cancel.
