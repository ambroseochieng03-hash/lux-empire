<?php
/**
 * LUX EMPIRE — HOUSE FILTERS MODAL (partial)
 * SAVE AT: includes/house_filter_modal.php
 *
 * Starts hidden. Opened via any element with data-open-house-filters.
 * All option values (house types, locations, price bounds) are
 * populated by house-filters.js from api/houses/filter_meta.php —
 * nothing here is hardcoded.
 */
?>

<div class="hf-modal" id="houseFilterModal" aria-hidden="true">

    <div class="hf-modal-overlay" data-hf-close></div>

    <div class="hf-modal-box" role="dialog" aria-modal="true">

        <button type="button" class="hf-modal-close" data-hf-close aria-label="Close">×</button>

        <h2 class="tenant-title" style="margin-top:0;">Filter Properties</h2>

        <div class="hf-field">
            <label>Price Range (KES)</label>
            <div class="hf-price-slider" id="hfPriceSlider">
                <div class="hf-price-track"></div>
                <div class="hf-price-track-fill" id="hfPriceTrackFill"></div>
                <input type="range" id="hfPriceMin" step="500">
                <input type="range" id="hfPriceMax" step="500">
            </div>
            <div class="hf-price-readout">
                <span id="hfPriceMinLabel">KES 0</span>
                <span id="hfPriceMaxLabel">KES 0</span>
            </div>
        </div>

        <div class="hf-field">
            <label for="hfHouseType">House Type</label>
            <select id="hfHouseType">
                <option value="">Any type</option>
            </select>
        </div>

        <div class="hf-field">
            <label for="hfLocation">Location</label>
            <input type="text" id="hfLocation" list="hfLocationList" placeholder="Search or pick a location...">
            <datalist id="hfLocationList"></datalist>
        </div>

        <div class="hf-field">
            <label for="hfInstitution">Near a university / college / institution</label>
            <select id="hfInstitution">
                <option value="">Any / not important</option>
            </select>
        </div>

        <div class="hf-field" id="hfDistanceField" style="display:none;">
            <label for="hfMaxDistance">Maximum distance</label>
            <select id="hfMaxDistance">
                <option value="1">Within 1 km</option>
                <option value="3">Within 3 km</option>
                <option value="5" selected>Within 5 km</option>
                <option value="10">Within 10 km</option>
                <option value="20">Within 20 km</option>
            </select>
        </div>

        <div class="hf-field">
            <label for="hfBedrooms">Bedrooms (min)</label>
            <select id="hfBedrooms">
                <option value="">Any</option>
                <option value="1">1+</option>
                <option value="2">2+</option>
                <option value="3">3+</option>
                <option value="4">4+</option>
            </select>
        </div>

        <div class="hf-field">
            <label for="hfBathrooms">Bathrooms (min)</label>
            <select id="hfBathrooms">
                <option value="">Any</option>
                <option value="1">1+</option>
                <option value="2">2+</option>
                <option value="3">3+</option>
            </select>
        </div>

        <div class="hf-field">
            <label for="hfSort">Sort By</label>
            <select id="hfSort">
                <option value="newest">Newest First</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>

        <div class="hf-modal-actions">
            <button type="button" class="lux-btn" id="hfResetBtn">Reset</button>
            <button type="button" class="lux-btn" id="hfApplyBtn">Apply Filters</button>
        </div>

    </div>

</div>