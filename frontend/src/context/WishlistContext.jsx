import * as React from 'react';

const defaultValue = { wishlist: [], toggleWishlist: async () => {}, removeWishlist: async () => {}, wishlistPending: new Set(), wishlistLoading: false, wishlistError: null };
const WishlistContext = React.createContext(defaultValue);

export function WishlistProvider({ value, children }) {
  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>;
}

export function useWishlist() {
  return React.useContext(WishlistContext);
}
