<x-select name="category" :label="__('Category')" :options="\App\Enums\OtherAssetCategory::options()" :value="request('category')" placeholder="{{ __('All') }}" span="2" />
