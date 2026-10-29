import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import Cart from '../pages/Cart';

jest.mock('../services/apiClient', () => ({
  apiClient: {
    get: jest.fn().mockResolvedValue({ data: { data: { ordersEnabled: true } } }),
  },
}));

const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}));

describe('<Cart />', () => {
  it('shows empty message when cart is empty', () => {
    render(<Cart cart={[]} setCart={jest.fn()} />);
    expect(screen.getByText(/Your cart is waiting to be discovered/i)).toBeInTheDocument();
    expect(screen.queryByText(/Total:/i)).not.toBeInTheDocument();
  });

  it('displays items and total correctly', () => {
    const items = [
      { id: '1', name: 'Widget', price: 9.99, image: '' },
      { id: '2', name: 'Gizmo', price: 5.01, image: '' },
    ];
    render(<Cart cart={items} setCart={jest.fn()} />);
    // list items
    expect(screen.getByText('Widget')).toBeInTheDocument();
    expect(screen.getByText('Gizmo')).toBeInTheDocument();
    // total = 9.99 + 5.01 = 15.00
    expect(screen.getByText(/Rs\. 15/)).toBeInTheDocument();
  });

  it('calls setCart when Remove is clicked', () => {
    const items = [
      { id: '1', name: 'Widget', price: 9.99, image: '' },
      { id: '2', name: 'Gizmo', price: 5.01, image: '' },
    ];
    const setCart = jest.fn();
    render(<Cart cart={items} setCart={setCart} />);
    const removeButtons = screen.getAllByRole('button', { name: /remove/i });
    fireEvent.click(removeButtons[0]);
    expect(setCart).toHaveBeenCalledWith([{ id: '2', name: 'Gizmo', price: 5.01, image: '' }]);
  });

  it('requires a guest to sign in before checkout', async () => {
    const items = [{ id: '1', name: 'Widget', price: 9.99, image: '' }];
    render(<Cart cart={items} setCart={jest.fn()} />);
    const button = await screen.findByRole('button', { name: /sign in to checkout/i });
    fireEvent.click(button);
    expect(mockNavigate).toHaveBeenCalledWith('/login?returnTo=%2Fcheckout');
  });
});
