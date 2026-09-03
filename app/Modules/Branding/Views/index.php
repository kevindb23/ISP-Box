<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="branding"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The branding interface is not built.</div><?php endif;return;?>
<div class="container-fluid nx-page" id="brandingPage">
    <div class="card border-0 shadow-sm nx-page-header-card">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="branding-hero-badge">
                    <i class="bi bi-palette"></i>
                    <span>BRANDING</span>
                </div>

                <h5 class="nx-page-title">Company Settings</h5>
                <div class="nx-page-subtitle">
                    Manage company identity used across the portal, invoices, receipts, customer portal, and login page.
                </div>
            </div>

            <div class="nx-page-actions">
                <button type="button" class="btn btn-primary nx-header-btn" id="btnSaveBranding">
                    <i class="bi bi-save"></i>
                    <span>Save Branding</span>
                </button>

                <button type="button" class="btn btn-light border nx-header-btn" id="btnRefreshBranding">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <form id="brandingForm" enctype="multipart/form-data">
        <div class="row g-3 mb-3">

            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm nx-content-card content-card h-100">
                    <div class="card-body">
                        <div class="branding-section-title">
                            <i class="bi bi-building text-primary"></i>
                            <span>Company Information</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="companyName" name="company_name">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="logoText">Logo Text</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="logoText"
                                    name="logo_text"
                                    maxlength="60"
                                    placeholder="Example: 1WAN"
                                >
                                <div class="form-text">Displayed beside the logo on the login page.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Company Address</label>
                                <textarea class="form-control" id="companyAddress" name="company_address" rows="3"></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Support Email</label>
                                <input type="email" class="form-control" id="supportEmail" name="support_email">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Support Phone</label>
                                <input type="text" class="form-control" id="supportPhone" name="support_phone">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">TIN</label>
                                <input type="text" class="form-control" id="companyTin" name="tin">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" id="companyWebsite" name="website" placeholder="https://example.com">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Company Logo</label>

                                <input type="hidden" id="logoPath" name="logo_path">
                                <input type="hidden" id="removeLogo" name="remove_logo" value="0">

                                <div class="branding-logo-grid">
                                    <div class="branding-logo-current">
                                        <div class="branding-logo-preview" id="brandingLogoPreview">
                                            <i class="bi bi-hdd-network"></i>
                                        </div>

                                        <button type="button" class="btn btn-outline-danger w-100 mt-2" id="btnRemoveLogo">
                                            <i class="bi bi-trash"></i>
                                            <span>Remove Logo</span>
                                        </button>
                                    </div>

                                    <label class="branding-upload-box" for="companyLogoInput">
                                        <input type="file" id="companyLogoInput" name="company_logo" accept="image/png,image/jpeg,image/webp">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <strong>Upload Logo</strong>
                                        <span>PNG, JPG or WEBP. Max 2MB.</span>
                                        <small>Recommended size: 300x100px</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm nx-content-card content-card h-100">
                    <div class="card-body">
                        <div class="branding-section-title">
                            <i class="bi bi-eye text-primary"></i>
                            <span>Preview</span>
                        </div>

                        <div class="branding-preview-box">
                            <div class="branding-preview-logo" id="brandingPreviewLogo">
                                <i class="bi bi-hdd-network"></i>
                            </div>

                            <div class="branding-preview-copy">
                                <div class="branding-preview-name" id="brandingPreviewName">1WAN</div>
                                <div class="branding-preview-subtitle brand-powered-label">Powered by 1WAN</div>
                            </div>
                        </div>

                        <div class="text-muted small mt-3">
                            This preview shows the logo and editable logo text used on the login page.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <script src="/module-assets/Branding/js/branding.js"></script>
</div>
