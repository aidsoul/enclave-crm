const PHONE_RE = /^\+?[0-9\s\-\(\)]{7,20}$/u;

function isFilled(value) {
  return value !== undefined && value !== null && String(value).trim() !== '';
}

function isNumeric(value) {
  if (value === null || value === undefined || String(value).trim() === '') return true;
  return Number.isFinite(Number(String(value).replace(',', '.')));
}

function toNumber(value) {
  if (value === null || value === undefined || String(value).trim() === '') return null;
  return Number(String(value).replace(',', '.'));
}

function hasLeadingZero(value) {
  if (value === null || value === undefined) return false;
  const str = String(value).trim();
  return /^0\d+/.test(str);
}

export function validateOrderForm(form = {}) {
  const errors = {};

  if ('site_id' in form) {
    if (!isFilled(form.site_id)) {
      errors.site_id = 'Выберите сайт.';
    }
  }

  if ('manager_id' in form && isFilled(form.manager_id)) {
    if (!Number.isInteger(Number(form.manager_id))) {
      errors.manager_id = 'Некорректный менеджер.';
    }
  }

  if ('delivery_courier_id' in form && isFilled(form.delivery_courier_id)) {
    if (!Number.isInteger(Number(form.delivery_courier_id))) {
      errors.delivery_courier_id = 'Некорректный курьер.';
    }
  }

  if ('client_name' in form || ('client_data' in form && form.client_data)) {
    const name = String(form.client_name ?? form.client_data?.name ?? '').trim();
    if (!name) {
      errors.client_name = 'Укажите имя клиента.';
    } else if (name.length > 255) {
      errors.client_name = 'Имя клиента не должно превышать 255 символов.';
    }
  }

  if ('client_phone' in form || ('client_data' in form && form.client_data)) {
    const phone = String(form.client_phone ?? form.client_data?.phone ?? '').trim();
    if (!phone) {
      errors.client_phone = 'Укажите телефон клиента.';
    } else if (phone.length > 20) {
      errors.client_phone = 'Телефон не должен превышать 20 символов.';
    } else if (!PHONE_RE.test(phone)) {
      errors.client_phone = 'Некорректный формат телефона. Пример: +375 29 123-45-67.';
    }
  }

  if ('client_email' in form || ('client_data' in form && form.client_data)) {
    const email = String(form.client_email ?? form.client_data?.email ?? '').trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.client_email = 'Некорректный формат email.';
    }
  }

  if ('client_address' in form || ('client_data' in form && form.client_data)) {
    const address = String(form.client_address ?? form.client_data?.address ?? '').trim();
    if (!address) {
      errors.client_address = 'Укажите адрес доставки.';
    } else if (address.length > 1000) {
      errors.client_address = 'Адрес не должен превышать 1000 символов.';
    }
  }

  if ('order_items' in form && Array.isArray(form.order_items)) {
    if (form.order_items.length > 100) {
      errors.order_items = 'Слишком много позиций в заказе (максимум 100).';
    }
    form.order_items.forEach((item, index) => {
      const name = String(item?.name ?? '').trim();
      if (!name) {
        errors[`order_items.${index}.name`] = 'Укажите название товара.';
      } else if (name.length > 255) {
        errors[`order_items.${index}.name`] = 'Название товара не должно превышать 255 символов.';
      }

      const qty = item?.qty;
      if (isFilled(qty)) {
        if (hasLeadingZero(qty)) {
          errors[`order_items.${index}.qty`] = 'Количество не должно начинаться с нуля.';
        } else if (!Number.isInteger(Number(qty))) {
          errors[`order_items.${index}.qty`] = 'Количество должно быть целым числом.';
        } else if (Number(qty) < 1) {
          errors[`order_items.${index}.qty`] = 'Количество должно быть не меньше 1.';
        } else if (Number(qty) > 10000) {
          errors[`order_items.${index}.qty`] = 'Количество не должно превышать 10000.';
        }
      }

      const price = item?.price;
      if (isFilled(price)) {
        if (hasLeadingZero(price)) {
          errors[`order_items.${index}.price`] = 'Цена не должна начинаться с нуля.';
        } else if (!isNumeric(price)) {
          errors[`order_items.${index}.price`] = 'Цена должна быть числом.';
        } else if (toNumber(price) < 0) {
          errors[`order_items.${index}.price`] = 'Цена не может быть отрицательной.';
        }
      }

      const purchasePrice = item?.purchase_price;
      if (isFilled(purchasePrice)) {
        if (hasLeadingZero(purchasePrice)) {
          errors[`order_items.${index}.purchase_price`] = 'Закупочная цена не должна начинаться с нуля.';
        } else if (!isNumeric(purchasePrice)) {
          errors[`order_items.${index}.purchase_price`] = 'Закупочная цена должна быть числом.';
        } else if (toNumber(purchasePrice) < 0) {
          errors[`order_items.${index}.purchase_price`] = 'Закупочная цена не может быть отрицательной.';
        }
      }
    });
  }

  if ('order_content' in form && Array.isArray(form.order_content)) {
    if (form.order_content.length > 100) {
      errors.order_content = 'Слишком много позиций в заказе (максимум 100).';
    }
    form.order_content.forEach((item, index) => {
      const name = String(item?.name ?? '').trim();
      if (!name) {
        errors[`order_content.${index}.name`] = 'Укажите название товара.';
      } else if (name.length > 255) {
        errors[`order_content.${index}.name`] = 'Название товара не должно превышать 255 символов.';
      }

      const qty = item?.qty;
      if (isFilled(qty)) {
        if (hasLeadingZero(qty)) {
          errors[`order_content.${index}.qty`] = 'Количество не должно начинаться с нуля.';
        } else if (!Number.isInteger(Number(qty))) {
          errors[`order_content.${index}.qty`] = 'Количество должно быть целым числом.';
        } else if (Number(qty) < 1) {
          errors[`order_content.${index}.qty`] = 'Количество должно быть не меньше 1.';
        } else if (Number(qty) > 10000) {
          errors[`order_content.${index}.qty`] = 'Количество не должно превышать 10000.';
        }
      }

      const price = item?.price;
      if (isFilled(price)) {
        if (hasLeadingZero(price)) {
          errors[`order_content.${index}.price`] = 'Цена не должна начинаться с нуля.';
        } else if (!isNumeric(price)) {
          errors[`order_content.${index}.price`] = 'Цена должна быть числом.';
        } else if (toNumber(price) < 0) {
          errors[`order_content.${index}.price`] = 'Цена не может быть отрицательной.';
        }
      }

      const purchasePrice = item?.purchase_price;
      if (isFilled(purchasePrice)) {
        if (hasLeadingZero(purchasePrice)) {
          errors[`order_content.${index}.purchase_price`] = 'Закупочная цена не должна начинаться с нуля.';
        } else if (!isNumeric(purchasePrice)) {
          errors[`order_content.${index}.purchase_price`] = 'Закупочная цена должна быть числом.';
        } else if (toNumber(purchasePrice) < 0) {
          errors[`order_content.${index}.purchase_price`] = 'Закупочная цена не может быть отрицательной.';
        }
      }
    });
  }

  if ('delivery_price' in form && isFilled(form.delivery_price)) {
    if (hasLeadingZero(form.delivery_price)) {
      errors.delivery_price = 'Стоимость доставки не должна начинаться с нуля.';
    } else if (!isNumeric(form.delivery_price)) {
      errors.delivery_price = 'Стоимость доставки должна быть числом.';
    } else if (toNumber(form.delivery_price) < 0) {
      errors.delivery_price = 'Стоимость доставки не может быть отрицательной.';
    }
  }

  if ('total_price' in form) {
    if (!isFilled(form.total_price)) {
      errors.total_price = 'Укажите цену для клиента.';
    } else if (hasLeadingZero(form.total_price)) {
      errors.total_price = 'Цена для клиента не должна начинаться с нуля.';
    } else if (!isNumeric(form.total_price)) {
      errors.total_price = 'Цена для клиента должна быть числом.';
    } else if (toNumber(form.total_price) < 0) {
      errors.total_price = 'Цена для клиента не может быть отрицательной.';
    }
  }

  if ('purchase_price' in form && isFilled(form.purchase_price)) {
    if (hasLeadingZero(form.purchase_price)) {
      errors.purchase_price = 'Закупочная цена не должна начинаться с нуля.';
    } else if (!isNumeric(form.purchase_price)) {
      errors.purchase_price = 'Закупочная цена должна быть числом.';
    } else if (toNumber(form.purchase_price) < 0) {
      errors.purchase_price = 'Закупочная цена не может быть отрицательной.';
    }
  }

  return errors;
}

export function hasErrors(errors) {
  return Object.keys(errors).length > 0;
}

export function extractServerErrors(error) {
  try {
    if (!error?.body) return {};
    const body = JSON.parse(error.body);
    const messages = body?.errors;
    if (!messages || typeof messages !== 'object') return {};
    const result = {};
    for (const [field, list] of Object.entries(messages)) {
      if (Array.isArray(list) && list.length > 0) {
        result[field] = list[0];
      }
    }
    return result;
  } catch (e) {
    return {};
  }
}

export function clearError(errors, field) {
  if (field in errors) {
    delete errors[field];
  }
}
