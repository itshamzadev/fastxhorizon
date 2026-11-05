import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Shop from '../pages/Shop';

// Mock ProductCard so it doesn’t invoke useNavigate
jest.mock('../components/ProductCard', () => ({ product }) => <div data-testid="product-card">{product.name}</div>);

describe('<Shop />', () => {
  const renderShop = props => render(<MemoryRouter><Shop {...props} /></MemoryRouter>);
  const makeProducts = (n, category = 'cat') =>
    Array.from({ length: n }, (_, i) => ({
      id: String(i + 1),
      name: `Item${i + 1}`,
      category,
    }));

  it('shows loading spinner when loading=true', () => {
    renderShop({ products: [], addToCart: () => {}, loading: true });
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('renders header and no cards when products empty', () => {
    renderShop({ products: [], addToCart: () => {}, loading: false });
    expect(screen.getByText(/^Shop$/)).toBeInTheDocument();
    expect(screen.queryAllByTestId('product-card')).toHaveLength(0);
  });

  it('renders up to 24 product cards per page', () => {
    const products = makeProducts(24);
    renderShop({ products, addToCart: () => {}, loading: false });
    expect(screen.getAllByTestId('product-card')).toHaveLength(24);
  });

  it('paginates when more than 24 products', async () => {
    const twentySix = makeProducts(26);
    renderShop({ products: twentySix, addToCart: () => {}, loading: false });

    expect(screen.getAllByTestId('product-card')).toHaveLength(24);

    // Click the "Go to page 2" button
    fireEvent.click(screen.getByRole('button', { name: /go to page 2/i }));

    // Page 2 contains the remaining 2 cards.
    const page2Cards = await screen.findAllByTestId('product-card');
    expect(page2Cards).toHaveLength(2);
  });
});
