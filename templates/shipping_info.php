<div id="shippingInfo">
  <div class="adminInfo">
    <span class="label">Tracking</span>
    <span class="value"><?php echo $info['tracking_number']; ?></span>

    <span class="label">RMA Number</span>
    <span class="value"><?php echo $info['rma_number']; ?></span>

    <?php if($info['label_download']) : ?>
      <span class="label">Download Your Label</span>
      <div class="download-links">
        <?php
        foreach ($info['label_download'] as $key => $download) :
          echo '<a class="shipping-button value download" href="' . $download . '">' . $key . '</a>';
        endforeach;
        ?>
      </div>
    <?php endif; ?>
  </div>

</div>
