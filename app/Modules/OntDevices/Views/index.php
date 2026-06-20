<div class="container-fluid nx-page" id="ontDevicesPage">
    <link rel="stylesheet" href="/module-assets/OntDevices/css/OntDevices.css">

    <?php
    $tab = $tab ?? ($_GET['tab'] ?? 'inventory');
    ?>

    <div id="ontDevicesApp" data-initial-tab="<?= htmlspecialchars((string)$tab) ?>">

        <!-- HERO -->
        <div class="card border-0 shadow-sm mb-4 nx-page-header-card">
            <div class="card-body nx-page-header">
                <div class="nx-page-header-left">
                    <div class="ont-hero-badge">
                        <i class="bi bi-hdd-network"></i>
                        <span>Access Device Inventory &amp; ACS Operations</span>
                    </div>

                    <h1 class="nx-page-title">ONT Devices</h1>

                    <p class="nx-page-subtitle mb-0" id="ontPageSubtitle">
                        Inventory, OLT autofind, and ACS remote visibility
                    </p>
                </div>

                <div id="ontHeaderActions" class="nx-page-actions"></div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="card border-0 shadow-sm mb-4 nx-toolbar-card">
            <div class="card-body">
                <div class="ont-toolbar-shell">
                    <div class="ont-toolbar-left">
                        <div class="nx-segment-control ont-segment-control">
                            <a href="#"
                               class="nx-segment-item <?= $tab === 'inventory' ? 'active' : '' ?>"
                               data-tab-link="inventory">
                                <i class="bi bi-box-seam"></i>
                                <span>Inventory</span>
                            </a>

                            <a href="#"
                               class="nx-segment-item <?= $tab === 'discovery' ? 'active' : '' ?>"
                               data-tab-link="discovery">
                                <i class="bi bi-router"></i>
                                <span>Discovery</span>
                            </a>

                            <a href="#"
                               class="nx-segment-item <?= $tab === 'remote-management' ? 'active' : '' ?>"
                               data-tab-link="remote-management">
                                <i class="bi bi-broadcast"></i>
                                <span>Remote Management</span>
                            </a>
                        </div>
                    </div>

                    <div class="ont-toolbar-right" id="ontToolbarArea"></div>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div id="ontContentArea"></div>
    </div>

    <!-- ADD / EDIT ONT MODAL -->
    <div class="modal fade nx-modal" id="ontModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <form id="ontForm" data-mode="create" data-id="">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="ontModalTitle">Add ONT</h5>
                            <small class="text-muted" id="ontModalSubtitle">Create inventory record</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="nx-section-card">
                            <div class="nx-grid-2">
                                <div class="mb-2">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" name="serial_number" id="ontSerialInput" class="form-control" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Vendor</label>
                                    <select name="vendor" id="ontVendorInput" class="form-select">
                                        <option value="">Select Vendor</option>
                                        <option value="Huawei">Huawei</option>
                                        <option value="Nokia">Nokia</option>
                                        <option value="ZTE">ZTE</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Model</label>
                                    <input type="text" name="model" id="ontModelInput" class="form-control">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Equipment ID</label>
                                    <input type="text" name="equipment_id" id="ontEquipmentIdInput" class="form-control">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">MAC Address</label>
                                    <input type="text" name="mac_address" id="ontMacInput" class="form-control">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Subscriber ID</label>
                                    <input type="number" name="subscriber_id" id="ontSubscriberIdInput" class="form-control">
                                </div>

                                <div class="mb-2 nx-span-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="ontStatusInput" class="form-select">
                                        <option value="UNASSIGNED">UNASSIGNED</option>
                                        <option value="ASSIGNED">ASSIGNED</option>
                                        <option value="OFFLINE">OFFLINE</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="ontSubmitBtn">
                            <i class="bi bi-check-circle"></i>
                            <span>Save</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- INVENTORY VIEW MODAL -->
    <div class="modal fade nx-modal" id="inventoryViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">ONT Inventory Details</h5>
                        <small class="text-muted" id="inventoryViewSubtitle">Inspect ONT inventory record</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-section-card">
                        <div class="nx-grid-2">
                            <div class="nx-field">
                                <label>Serial Number</label>
                                <div id="viewInventorySerial">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Status</label>
                                <div id="viewInventoryStatus">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Vendor</label>
                                <div id="viewInventoryVendor">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Model</label>
                                <div id="viewInventoryModel">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Equipment ID</label>
                                <div id="viewInventoryEquipmentId">-</div>
                            </div>

                            <div class="nx-field">
                                <label>MAC Address</label>
                                <div id="viewInventoryMac">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Subscriber ID</label>
                                <div id="viewInventorySubscriberId">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Subscriber Name</label>
                                <div id="viewInventorySubscriberName">-</div>
                            </div>

                            <div class="nx-field nx-span-2">
                                <label>Created At</label>
                                <div id="viewInventoryCreatedAt">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="inventoryCopySerialBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy Serial</span>
                    </button>
                    <button type="button" class="btn btn-primary" id="inventoryEditFromViewBtn">
                        <i class="bi bi-pencil"></i>
                        <span>Edit</span>
                    </button>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DISCOVERY VIEW MODAL -->
    <div class="modal fade nx-modal" id="discoveryViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Discovered ONT Details</h5>
                        <small class="text-muted" id="discoveryViewSubtitle">Inspect OLT autofind record</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-section-card">
                        <div class="nx-grid-2">
                            <div class="nx-field">
                                <label>Serial Number</label>
                                <div id="viewDiscoverySerial">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Inventory State</label>
                                <div id="viewDiscoveryInventoryState">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Model</label>
                                <div id="viewDiscoveryModel">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Vendor</label>
                                <div id="viewDiscoveryVendor">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Vendor Code</label>
                                <div id="viewDiscoveryVendorCode">-</div>
                            </div>

                            <div class="nx-field">
                                <label>F/S/P</label>
                                <div id="viewDiscoveryFsp">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Frame / Slot / Port</label>
                                <div id="viewDiscoveryPhysical">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Last Seen</label>
                                <div id="viewDiscoveryLastSeen">-</div>
                            </div>

                            <div class="nx-field nx-span-2">
                                <label>Autofind Time</label>
                                <div id="viewDiscoveryAutofindTime">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="discoveryCopySerialBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy Serial</span>
                    </button>
                    <button type="button" class="btn btn-primary" id="discoveryModalAddBtn">
                        <i class="bi bi-plus-lg"></i>
                        <span>Add to Inventory</span>
                    </button>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ACS VIEW MODAL -->
    <div class="modal fade nx-modal" id="acsViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">ACS Device Details</h5>
                        <small class="text-muted" id="acsViewSubtitle">Inspect ACS device record</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-section-card">
                        <div class="nx-grid-2">
                            <div class="nx-field">
                                <label>Device ID</label>
                                <div id="viewAcsDeviceId">-</div>
                            </div>

                            <div class="nx-field">
                                <label>ACS Status</label>
                                <div id="viewAcsStatus">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Serial Number</label>
                                <div id="viewAcsSerial">-</div>
                            </div>

                            <div class="nx-field">
                                <label>WAN IP</label>
                                <div id="viewAcsWanIp">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Vendor</label>
                                <div id="viewAcsVendor">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Model</label>
                                <div id="viewAcsModel">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Firmware</label>
                                <div id="viewAcsFirmware">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Uptime</label>
                                <div id="viewAcsUptime">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Last Seen</label>
                                <div id="viewAcsLastSeen">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Inventory Match</label>
                                <div id="viewAcsInventoryMatch">-</div>
                            </div>

                            <div class="nx-field nx-span-2">
                                <label>Linked Inventory Info</label>
                                <div id="viewAcsInventoryInfo">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="acsCopyDeviceIdBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy Device ID</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="acsCopySerialBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy Serial</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="acsCopyWanIpBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy WAN IP</span>
                    </button>
                    <button type="button" class="btn btn-primary" id="acsOpenInventoryBtn">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>View Inventory</span>
                    </button>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/OntDevices/js/OntDevices.js"></script>
</div>