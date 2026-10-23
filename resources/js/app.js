import Alpine from 'alpinejs';
import forms from './forms';
import dialogs from './components/dialogs';
import productForm from './components/product-form';
import productPage from './components/product-page';
import regionPicker from './components/region-picker';
import bannerCarousel from './components/banner-carousel';
import checkout from './components/checkout';
import wishlistButton from './components/wishlist-button';
import firebaseLogin from './components/firebase-login';
import articleEditor from './components/article-editor';
import shareButtons from './components/share-buttons';

window.Alpine = Alpine;

Alpine.plugin(forms);
Alpine.plugin(dialogs);

/** Format angka ke rupiah, dipakai di komponen Alpine. */
window.rupiah = (value) => 'Rp' + new Intl.NumberFormat('id-ID').format(value || 0);

Alpine.data('productForm', productForm);
Alpine.data('productPage', productPage);
Alpine.data('regionPicker', regionPicker);
Alpine.data('checkout', checkout);
Alpine.data('bannerCarousel', bannerCarousel);
Alpine.data('wishlistButton', wishlistButton);
Alpine.data('firebaseLogin', firebaseLogin);
Alpine.data('articleEditor', articleEditor);
Alpine.data('shareButtons', shareButtons);

Alpine.start();
