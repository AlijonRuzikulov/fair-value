<x-select name="category" :label="__('Category')" :options="\App\Enums\AssetCategory::options()" :value="request('category')" placeholder="{{ __('All') }}" span="2" />
