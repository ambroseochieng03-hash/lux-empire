<?php
/**
 * LUX EMPIRE — SHARED INFO MODAL (About / Contact / Privacy Policy)
 *
 * One modal shell, content swapped at click-time from the matching
 * <template>. Opened by any element with
 * [data-open-info-modal="about|contact|privacy"].
 * Requires assets/css/info-modals.css + assets/js/info-modals.js.
 *
 * CONTACT DETAILS BELOW ARE PLACEHOLDERS — replace before launch.
 * PRIVACY POLICY IS A DRAFT — have it reviewed by a lawyer before
 * relying on it in production.
 */
?>

<div class="info-modal" id="infoModal" aria-hidden="true">
    <div class="info-modal-overlay" data-info-modal-close></div>

    <div class="info-modal-box">
        <button type="button" class="info-modal-close" data-info-modal-close aria-label="Close">×</button>
        <div class="info-modal-content" id="infoModalContent">
            <!-- filled from the matching <template> below on open -->
        </div>
    </div>
</div>

<template id="infoModalTemplate-about">
    <h2 class="info-modal-title">About LUX EMPIRE</h2>
    <div class="info-modal-body">
        <p>LUX EMPIRE brings luxury property and logistics together in one place. Browse elite homes and apartments from verified landlords, then move in without the hassle — request a truck, track your driver live, and get everything handled by one connected platform.</p>
        <p>Whether you're a landlord listing a property, a tenant hunting for your next home, or a driver offering moving services, LUX EMPIRE gives you a secure, modern space to connect — built with real-time chat, live GPS tracking, and encrypted handling of your sensitive details from day one.</p>
    </div>
</template>

<template id="infoModalTemplate-contact">
    <h2 class="info-modal-title">Contact Us</h2>
    <div class="info-modal-body">
        <p>Have a question, an issue with your account, or feedback for the Empire? Reach us through any of the channels below.</p>
        <ul class="info-modal-contact-list">
            <li><i class="fa-solid fa-envelope"></i> <span>ambroseochieng03@gmail.com</span></li>
            <li><i class="fa-solid fa-phone"></i> <span>0116268903</span></li>
            <li><i class="fa-solid fa-location-dot"></i> <span>00100 Nairobi</span></li>
            <li><i class="fa-solid fa-clock"></i> <span>9:00 AM - 6:00 PM EST</span></li>
        </ul>
        <p class="info-modal-note">Placeholder contact details — will be replaced with real values before launch.</p>
    </div>
</template>

<template id="infoModalTemplate-privacy">
    <h2 class="info-modal-title">Privacy Policy</h2>
    <div class="info-modal-body info-modal-body-scroll">
        <!-- <p class="info-modal-note">Draft policy — reflects what the platform currently collects and how it is technically handled. Have this reviewed by a qualified lawyer before relying on it in production.</p> -->

        <h3>What we collect</h3>
        <p>Account details (full name, email, phone number), a securely hashed password, and — depending on your role — a national ID number (landlords) or driver's license, vehicle plate, and vehicle type (drivers). Tenants and drivers may also share live location data while using truck-tracking features, and messages sent through in-app chat with landlords or drivers.</p>

        <h3>How sensitive data is protected</h3>
        <p>Passwords are never stored in readable form — they are hashed using the Argon2id algorithm. National ID numbers, driver's license numbers, and vehicle plate details are encrypted at rest using authenticated encryption before being saved, and can only be decrypted by the platform when legitimately needed.</p>

        <h3>Location and tracking</h3>
        <p>If you are a driver on an active trip, or a tenant tracking a driver, your location is shared temporarily to power live tracking on the map. This data is used only for the duration of the relevant trip.</p>

        <h3>Communications</h3>
        <p>We send a one-time verification code by email when you register, to confirm you own the email address you signed up with. If you sign up using Google, we verify your identity through Google's sign-in service instead.</p>

        <h3>Cookies and sessions</h3>
        <p>We use a session cookie to keep you signed in. It is required for the platform to function and is not used for advertising or third-party tracking.</p>

        <h3>Your consent</h3>
        <p>Landlords and drivers are asked to explicitly accept a data-processing notice during registration before their account is created.</p>

        <h3>Contact</h3>
        <p>For questions about this policy or your data, reach out via the Contact page.</p>
    </div>
</template>
