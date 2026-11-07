import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Shop from '../pages/Shop';
import { apiClient } from '../services/apiClient';

jest.mock('../services/apiClient', () => ({
  apiClient: { get: jest.fn() },
}));

// Mock ProductCard so it doesn’t invoke useNavigate
jest.mock('../components/ProductCard', () => ({ product }) => <div data-testid="product-card">{product.name}</div>);

describe('<Shop />', () => {
  beforeEach(() => {
    apiClient.get.mockImplementation((_endpoint, { params } = {}) => {
      const total = 26;
      const start = ((params?.page || 1) - 1) * 24;
      const products = makeProducts(total).slice(start, start + 24);
      return Promise.resolve({ data: { products, total, page: params?.page || 1, limit: 24, totalPages: 2, hasMore: start + products.length < total } });
    });
  });
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

  it('renders header and no cards when products empty', async () => {
    apiClient.get.mockResolvedValueOnce({ data: { products: [], total: 0, page: 1, limit: 24, totalPages: 0, hasMore: false } });
    renderShop({ products: [], addToCart: () => {}, loading: false });
    expect(await screen.findByText(/^Shop$/)).toBeInTheDocument();
    expect(screen.queryAllByTestId('product-card')).toHaveLength(0);
  });

  it('renders up to 24 product cards per page', async () => {
    const products = makeProducts(24);
    renderShop({ products, addToCart: () => {}, loading: false });
    expect(await screen.findAllByTestId('product-card')).toHaveLength(24);
  });

  it('paginates when more than 24 products', async () => {
    const twentySix = makeProducts(26);
    renderShop({ products: twentySix, addToCart: () => {}, loading: false });

    expect(await screen.findAllByTestId('product-card')).toHaveLength(24);

    fireEvent.click(await screen.findByRole('button', { name: /Load More Products/i }));
    await screen.findByText(/Loaded 26 of 26 products/i);
    expect(screen.getAllByTestId('product-card')).toHaveLength(26);
    expect(apiClient.get.mock.calls.some(([, request]) => request.params.page === 2)).toBe(true);
  });
});
