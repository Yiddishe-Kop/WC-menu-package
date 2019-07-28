'use strict';

jQuery(document).ready(function ($) {
  if (!$('.wooco-wrap').length) {
    return;
  }

  var $wooco_wrap = $('.product-type-composite').eq(0);
  var $wooco_components = $('.wooco-components').eq(0);
  var $wooco_ids = $('.wooco-ids').eq(0);
  var $wooco_total = $('.wooco-total').eq(0);
  var $wooco_btn = $wooco_ids.closest('form.cart').find('.single_add_to_cart_button');
  if (!$wooco_btn.length) {
    $wooco_btn = $wooco_components.closest('.summary').find('.single_add_to_cart_button');
  }

  if (!$wooco_btn.length) {
    console.log('Have an issue with your template, so might WPC Composite Products doesn\'t work completely. Please contact us via email contact@wpclever.net to get the help.');
  }

  wooco_check_ready();

  $(document).on('click touch', '.single_add_to_cart_button', function (e) {
    var $this = $(this);

    if ($this.hasClass('wooco-disabled')) {
      if ($this.hasClass('wooco-selection')) {
        alert(wooco_vars.alert_selection);
      } else if ($this.hasClass('wooco-min')) {
        alert(wooco_vars.alert_min.replace('[min]', $wooco_components.attr('data-min')));
      } else if ($this.hasClass('wooco-max')) {
        alert(wooco_vars.alert_max.replace('[max]', $wooco_components.attr('data-max')));
      }
      e.preventDefault();
    }
  });

  if (wooco_vars.selector == 'ddslick') {
    $('.wooco_component_product_select').each(function () {
      var _this = $(this);
      var _this_selection = _this.closest('.wooco_component_product_selection');
      var _this_component = _this.closest('.wooco_component_product');
      _this.ddslick({
        width: '300px',
        onSelected: function (data) {
          var _selected = $(data.original[0].children[data.selectedIndex]);
          wooco_selected(_selected, _this_selection, _this_component);
          wooco_check_ready();
        }
      });
    });
  } else {
    $('.wooco_component_product_select').each(function () {
      //check on start
      var _this = $(this);
      var _this_selection = _this.closest('.wooco_component_product_selection');
      var _this_component = _this.closest('.wooco_component_product');
      var _selected = $("option:selected", this);
      wooco_selected(_selected, _this_selection, _this_component);
      wooco_check_ready();
    });

    $('body').on('change', '.wooco_component_product_select', function () {
      //check on select
      var _this = $(this);
      var _this_selection = _this.closest('.wooco_component_product_selection');
      var _this_component = _this.closest('.wooco_component_product');
      var _selected = $("option:selected", this);
      wooco_selected(_selected, _this_selection, _this_component);
      wooco_check_ready();
    });
  }

  function wooco_selected(selected, selection, component) {
    var selected_id = selected.attr('value');
    var selected_price = selected.attr('data-price');
    var selected_link = selected.attr('data-link');
    console.log({ selected_id, selected_price, selected_link });

    component.attr('data-id', selected_id);
    component.attr('data-price', selected_price);
    if (wooco_vars.product_link != 'no') {
      selection.find('.wooco_component_product_link').remove();
      if (selected_link != '') {
        if (wooco_vars.product_link == 'yes_popup') {
          selection.append('<a class="wooco_component_product_link woosq-btn" data-id="' + selected_id + '" href="' + selected_link + '" target="_blank"> &nbsp; </a>');
        } else {
          selection.append('<a class="wooco_component_product_link" href="' + selected_link + '" target="_blank"> &nbsp; </a>');
        }
      }
    }
    $(document).trigger('wooco_selected', [selected, selection, component]);
  }

  $('body').on('keyup change', '.wooco_component_product_qty_input', function () {
    var _val = parseInt($(this).val());
    var _min = parseInt($(this).attr('min'));
    var _max = parseInt($(this).attr('max'));
    if ((
      _val < _min
    ) || isNaN(_val)) {
      _val = _min;
      $(this).val(_val);
    }
    if (_val > _max) {
      _val = _max;
      $(this).val(_val);
    }
    $(this).closest('.wooco_component_product').attr('data-qty', _val);
    wooco_check_ready();
  });

  function wooco_check_ready() {
    var is_selection = false;
    var is_min = false;
    var is_max = false;
    var qty = 0;
    var qty_min = parseInt($wooco_components.attr('data-min'));
    var qty_max = parseInt($wooco_components.attr('data-max'));

    $wooco_components.find('.wooco_component_product').each(function () {
      var _this = $(this);

      if (_this.attr('data-id') > 0) {
        qty += parseInt(_this.attr('data-qty'));
      }

      if ((
        _this.attr('data-optional') == 'no'
      ) && (
          _this.attr('data-id') <= 0
        )) {
        is_selection = true;
      }
    });

    if (is_selection) {
      $wooco_btn.addClass('wooco-selection');
    } else {
      $wooco_btn.removeClass('wooco-selection');
    }

    if (qty < qty_min) {
      is_min = true;
      $wooco_btn.addClass('wooco-min');
    } else {
      $wooco_btn.removeClass('wooco-min');
    }

    if (qty > qty_max) {
      is_max = true;
      $wooco_btn.addClass('wooco-max');
    } else {
      $wooco_btn.removeClass('wooco-max');
    }

    if (is_selection || is_min || is_max) {
      $wooco_btn.addClass('wooco-disabled');
    } else {
      $wooco_btn.removeClass('wooco-disabled');
    }

    wooco_calc_price();
    wooco_save_ids();
  }

  function wooco_calc_price() {
    var total = 0;
    if ((
      $wooco_components.attr('data-pricing') == 'only'
    ) && (
        $wooco_components.attr('data-price') > 0
      )) {
      total = Number($wooco_components.attr('data-price'));
    } else {
      // calc price
      $wooco_components.find('.wooco_component_product').each(function () {
        var _this = $(this);
        if ((_this.attr('data-price') > 0)
          && (_this.attr('data-qty') > 0)
          && (_this.attr('data-cost_extra') == 'yes')) {

          let qty = Number(_this.attr('data-qty'));
          let qtyFree = Number(_this.attr('data-qty_free'));
          total += Number(_this.attr('data-price')) * (qty - qtyFree);
        }
      });

      // discount
      if ((
        $wooco_components.attr('data-percent') > 0
      ) && (
          $wooco_components.attr('data-percent') < 100
        )) {
        total = total * (
          100 - Number($wooco_components.attr('data-percent'))
        ) / 100;
      }

      if ($wooco_components.attr('data-pricing') == 'include') {
        total += Number($wooco_components.attr('data-price'));
      }
    }
    var total_html = '<span class="woocommerce-Price-amount amount">';
    var total_formatted = wooco_format_money(total, wooco_vars.price_decimals, '', wooco_vars.price_thousand_separator, wooco_vars.price_decimal_separator);
    switch (wooco_vars.price_format) {
      case '%1$s%2$s':
        //left
        total_html += '<span class="woocommerce-Price-currencySymbol">' + wooco_vars.currency_symbol + '</span>' + total_formatted;
        break;
      case '%1$s %2$s':
        //left with space
        total_html += '<span class="woocommerce-Price-currencySymbol">' + wooco_vars.currency_symbol + '</span> ' + total_formatted;
        break;
      case '%2$s%1$s':
        //right
        total_html += total_formatted + '<span class="woocommerce-Price-currencySymbol">' + wooco_vars.currency_symbol + '</span>';
        break;
      case '%2$s %1$s':
        //right with space
        total_html += total_formatted + ' <span class="woocommerce-Price-currencySymbol">' + wooco_vars.currency_symbol + '</span>';
        break;
      default:
        //default
        total_html += '<span class="woocommerce-Price-currencySymbol">' + wooco_vars.currency_symbol + '</span> ' + total_formatted;
    }
    total_html += '</span>';
    if ((
      $wooco_components.attr('data-pricing') != 'only'
    ) && (
        parseFloat($wooco_components.attr('data-percent')) > 0
      ) && (
        parseFloat($wooco_components.attr('data-percent')) < 100
      )) {
      total_html += ' <small class="woocommerce-price-suffix">' + wooco_vars.saved_text.replace('[d]', wooco_round(parseFloat($wooco_components.attr('data-percent'))) + '%') + '</small>';
    }
    $wooco_total.html(wooco_vars.total_text + ' ' + total_html).slideDown();

    if (wooco_vars.change_price == 'yes') {
      // change the main price
      $wooco_wrap.find('.summary > .price').html(total_html);
    }

    $(document).trigger('wooco_calc_price', [total, total_formatted, total_html]);
  }

  function wooco_save_ids() {
    var wooco_ids = Array();
    $wooco_components.find('.wooco_component_product').each(function () {
      var _this = $(this);
      if ((
        _this.attr('data-id') > 0
      ) && (
          _this.attr('data-qty') > 0
        )) {
        wooco_ids.push(_this.attr('data-id') + '/' + _this.attr('data-qty'));
      }
    });
    $wooco_ids.val(wooco_ids.join(','));
  }

  function wooco_round(num) {
    return +(
      Math.round(num + "e+2") + "e-2"
    );
  }

  function wooco_format_money(number, places, symbol, thousand, decimal) {
    number = number || 0;
    places = !isNaN(places = Math.abs(places)) ? places : 2;
    symbol = symbol !== undefined ? symbol : "$";
    thousand = thousand || ",";
    decimal = decimal || ".";
    var negative = number < 0 ? "-" : "",
      i = parseInt(number = Math.abs(+number || 0).toFixed(places), 10) + "",
      j = 0;
    if (i.length > 3) {
      j = i.length % 3;
    }
    return symbol + negative + (
      j ? i.substr(0, j) + thousand : ""
    ) + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousand) + (
        places ? decimal + Math.abs(number - i).toFixed(places).slice(2) : ""
      );
  }

});
