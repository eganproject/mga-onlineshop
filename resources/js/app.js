import Alpine from 'alpinejs';
import productForm from './components/product-form';
import productPage from './components/product-page';
import regionPicker from './components/region-picker';
import checkout from './components/checkout';
import wishlistButton from './components/wishlist-button';

window.Alpine = Alpine;

/** Format angka ke rupiah, dipakai di komponen Alpine. */
window.rupiah = (value) => 'Rp' + new Intl.NumberFormat('id-ID').format(value || 0);

Alpine.data('productForm', productForm);
Alpine.data('productPage', productPage);
Alpine.data('regionPicker', regionPicker);
Alpine.data('checkout', checkout);
Alpine.data('wishlistButton', wishlistButton);

Alpine.start();
