<div class="card mb-3">
    <div class="card-header">
        <h3 class="mb-1">Homepage commerce sections</h3>
        <p class="fs-12 text-body-secondary mb-0">Control Deals, Featured Products and Categories independently. Product selection remains in the existing deal, product and category admin screens.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4"><div class="border rounded p-3 h-100">
                <h4 class="fs-16">Fast X Horizon Deals</h4>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="homepage_deals_setup[enabled]" value="1" {{ !empty($homepageDealsSetup['enabled']) ? 'checked' : '' }}><label class="form-check-label">Show active deal when valid products exist</label></div>
                <label class="form-label">Homepage product limit</label><input class="form-control" type="number" min="1" max="50" name="homepage_deals_setup[product_limit]" value="{{ $homepageDealsSetup['product_limit'] ?? 10 }}">
                <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="homepage_deals_setup[countdown_enabled]" value="1" {{ !empty($homepageDealsSetup['countdown_enabled']) ? 'checked' : '' }}><label class="form-check-label">Show real deal countdown</label></div>
            </div></div>
            <div class="col-lg-4"><div class="border rounded p-3 h-100">
                <h4 class="fs-16">Featured Products</h4>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="homepage_featured_setup[enabled]" value="1" {{ !empty($homepageFeaturedSetup['enabled']) ? 'checked' : '' }}><label class="form-check-label">Show selected featured products</label></div>
                <label class="form-label">Section title</label><input class="form-control" type="text" maxlength="100" name="homepage_featured_setup[title]" value="{{ $homepageFeaturedSetup['title'] ?? 'Featured Products' }}">
                <label class="form-label mt-3">Homepage product limit</label><input class="form-control" type="number" min="1" max="50" name="homepage_featured_setup[product_limit]" value="{{ $homepageFeaturedSetup['product_limit'] ?? 12 }}">
            </div></div>
            <div class="col-lg-4"><div class="border rounded p-3 h-100">
                <h4 class="fs-16">Homepage Categories</h4>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="homepage_categories_setup[enabled]" value="1" {{ !empty($homepageCategoriesSetup['enabled']) ? 'checked' : '' }}><label class="form-check-label">Show admin-enabled categories</label></div>
                <label class="form-label">Homepage category limit</label><input class="form-control" type="number" min="1" max="50" name="homepage_categories_setup[product_limit]" value="{{ $homepageCategoriesSetup['product_limit'] ?? 8 }}">
                <p class="fs-12 text-body-secondary mt-3 mb-0">Use Category Management for Home Status, hierarchy, icon and priority.</p>
            </div></div>
        </div>
    </div>
</div>
