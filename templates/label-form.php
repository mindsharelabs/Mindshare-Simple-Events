<form id="shippingLabelForm">
  <?php mapi_write_log($origin); ?>
  <div class="originAddress">
    <h3>Origin Address</h3>
    <p class="label"><label for="origin_first_name">First name</label></p>
    <input type="text" style="" name="origin_first_name" id="origin_first_name" value="<?php echo (isset($origin['first_name'])) ? $origin['first_name'] : ''; ?>" placeholder="">

    <p class="label"><label for="origin_last_name">Last name</label></p>
    <input type="text" style="" name="origin_last_name" id="origin_last_name" value="<?php echo (isset($origin['last_name'])) ? $origin['last_name'] : ''; ?>" placeholder="">


    <p class="label"><label for="origin_company">Company</label></p>
    <input type="text" style="" name="origin_company" id="origin_company" value="<?php echo (isset($origin['company'])) ? $origin['company'] : ''; ?>" placeholder="">

    <p class="label"><label for="origin_address_1">Address line 1</label></p>
    <input type="text" style="" name="origin_address_1" id="origin_address_1" value="<?php echo (isset($origin['address_1'])) ? $origin['address_1'] : ''; ?>" placeholder="">


    <p class="label"><label for="origin_address_2">Address line 2</label></p>
    <input type="text" style="" name="origin_address_2" id="origin_address_2" value="<?php echo (isset($origin['address_2'])) ? $origin['address_2'] : ''; ?>" placeholder="">


    <p class="label"><label for="origin_city">City</label></p>
    <input type="text" style="" name="origin_city" id="origin_city" value="<?php echo (isset($origin['city'])) ? $origin['city'] : ''; ?>" placeholder="">

    <p class="label"><label for="origin_postcode">Postcode / ZIP</label></p>
    <input type="text" style="" name="origin_postcode" id="origin_postcode" value="<?php echo (isset($origin['postcode'])) ? $origin['postcode'] : ''; ?>" placeholder="">

    <p class="label"><label for="origin_country">Country</label></p>
    <div class="select-wrap">
      <select style="" id="origin_country" name="origin_country">
    		<option value="">Select a country…</option>
        <?php
        foreach ($countries as $key => $country) :
          echo '<option value="' . $key . '" ' . selected( $origin['country'], $key, false) . '>' . $country . '</option>';
        endforeach;
        ?>
      </select>
    </div>


    <p class="label"><label for="origin_state">State / County</label></p>
    <div class="select-wrap">
      <select id="origin_state" name="origin_state">
        <option value="">Select an option…</option>
        <?php
        foreach ($origin_states as $key => $state) :
          echo '<option value="' . $key . '" ' . selected( $origin['state'], $key, false) . '>' . $state . '</option>';
        endforeach;
        ?>
      </select>
    </div>



  </div>


  <?php mapi_write_log($destination); ?>
  <div class="desinationAddress">
    <h3>Destination Address</h3>
    <p class="label"><label for="destination_first_name">First name</label></p>
    <input type="text" style="" name="destination_first_name" id="destination_first_name" value="<?php echo (isset($destination['first_name'])) ? $destination['first_name'] : ''; ?>" placeholder="">

    <p class="label"><label for="destination_last_name">Last name</label></p>
    <input type="text" style="" name="destination_last_name" id="destination_last_name" value="<?php echo (isset($destination['last_name'])) ? $destination['last_name'] : ''; ?>" placeholder="">


    <p class="label"><label for="destination_company">Company</label></p>
    <input type="text" style="" name="destination_company" id="destination_company" value="<?php echo (isset($destination['company'])) ? $destination['company'] : ''; ?>" placeholder="">

    <p class="label"><label for="destination_address_1">Address line 1</label></p>
    <input type="text" style="" name="destination_address_1" id="destination_address_1" value="<?php echo (isset($destination['address_1'])) ? $destination['address_1'] : ''; ?>" placeholder="">


    <p class="label"><label for="destination_address_2">Address line 2</label></p>
    <input type="text" style="" name="destination_address_2" id="destination_address_2" value="<?php echo (isset($destination['address_2'])) ? $destination['address_2'] : ''; ?>" placeholder="">


    <p class="label"><label for="destination_city">City</label></p>
    <input type="text" style="" name="destination_city" id="destination_city" value="<?php echo (isset($destination['city'])) ? $destination['city'] : ''; ?>" placeholder="">

    <p class="label"><label for="destination_postcode">Postcode / ZIP</label></p>
    <input type="text" style="" name="destination_postcode" id="destination_postcode" value="<?php echo (isset($destination['postcode'])) ? $destination['postcode'] : ''; ?>" placeholder="">

    <p class="label"><label for="destination_country">Country</label></p>
    <div class="select-wrap">
      <select style="" id="destination_country" name="destination_country">
        <option value="">Select a country…</option>
        <?php
        foreach ($countries as $key => $country) :
          echo '<option value="' . $key . '" ' . selected( $origin['country'], $key, false) . '>' . $country . '</option>';
        endforeach;
        ?>
      </select>
    </div>


    <p class="label"><label for="destination_state">State / County</label></p>
    <div class="select-wrap">
      <select id="destination_state" name="destination_state">
        <option value="">Select an option…</option>
        <?php
        foreach ($destination_states as $key => $state) :
          echo '<option value="' . $key . '" ' . selected( $origin['state'], $key, false) . '>' . $state . '</option>';
        endforeach;
        ?>
      </select>
    </div>

  </div>
  <div class="break"></div>
  <div class="form-footer">
    <button data-action="mindreturns_generate_label" data-type="" data-orderid="<?php echo $orderID; ?>" class="mindreturns-action-button big button button-primary">Generate Shipping Label</button>

  </div>

</form>
