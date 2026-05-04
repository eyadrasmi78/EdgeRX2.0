
import React, { useState, useEffect } from 'react';
import { Login } from './components/Auth/Login';
import { Register } from './components/Auth/Register';
import { CustomerPortal } from './components/CustomerPortal';
import { SupplierPortal } from './components/SupplierPortal';
import { AdminPortal } from './components/AdminPortal';
import { CartDrawer } from './components/CartDrawer';
import { NotificationToast } from './components/NotificationToast';
import { Dashboard } from './components/Dashboard';
import { CustomerRequests } from './components/CustomerRequests';
import { BuyingGroups } from './components/BuyingGroups';
import { Transfers } from './components/Transfers';
import { PricingAgreements } from './components/PricingAgreements';
import { DataService } from './services/mockData';
import { subscribeUnauthorized } from './services/api';
import { setAiErrorHandler } from './services/aiService';
import { registerNotifier } from './services/notify';
import { ConfirmDialog } from './components/ConfirmDialog';
import { User, Product, Order, Notification, CartItem, UserRole, OrderStatus, RegistrationStatus } from './types';
import { useLanguage } from './contexts/LanguageContext';
import { LogOut, ShoppingCart, User as UserIcon, Bell, Home, Globe, LayoutGrid, ShoppingBag, Clock, Settings, CheckCircle, X, Clipboard, ExternalLink, Activity, BarChart3, Users, ShieldCheck, ArrowLeftRight, FileSignature } from 'lucide-react';

export function App() {
  const [user, setUser] = useState<User | null>(null);
  const [isRegistering, setIsRegistering] = useState(false);
  const [isBooting, setIsBooting] = useState(true);
  const [products, setProducts] = useState<Product[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [isCartOpen, setIsCartOpen] = useState(false);

  // Notification bell state — opens a dropdown listing in-app + server notifications
  const [isBellOpen, setIsBellOpen] = useState(false);
  const [serverNotifications, setServerNotifications] = useState<any[]>([]);
  const bellRef = React.useRef<HTMLDivElement>(null);

  // Navigation State
  const [activeView, setActiveView] = useState<string>('home');

  const { t, toggleLanguage, language, dir } = useLanguage();

  useEffect(() => {
    // Bootstrap: detect existing Sanctum session, hydrate caches.
    (async () => {
      try {
        const session = await DataService.bootstrap();
        if (session.user) {
          setUser(session.user);
          // Restore the persisted cart from the server so a refresh doesn't drop it.
          if (session.user.role === UserRole.CUSTOMER || session.user.role === UserRole.PHARMACY_MASTER) {
            const items = await DataService.loadCart();
            if (items.length) setCart(items);
          }
        }
        setProducts(DataService.getProducts());
        setOrders(DataService.getOrders());
      } finally {
        setIsBooting(false);
      }
    })();
  }, []);

  /**
   * FE-3 fix: subscribe to global 401 events. When any API call (cache refresh,
   * polling, mutation) gets a 401 because the session expired, we wipe local
   * state and force the user back to login — instead of leaving them in a
   * "logged in" UI that silently fails on every action.
   */
  useEffect(() => {
    const unsub = subscribeUnauthorized(() => {
      setUser(null);
      setCart([]);
      setOrders([]);
      setProducts([]);
      setIsBooting(false);
    });
    return unsub;
  }, []);

  /**
   * FE-20 fix: AI failures surface as toast notifications so the customer
   * understands why analysis/translation didn't work (rate limit, network,
   * bad prompt) instead of seeing a generic fallback string.
   */
  useEffect(() => {
    setAiErrorHandler((msg) => addNotification(msg, 'warning'));
    return () => setAiErrorHandler(null);
  }, []);

  /**
   * FE-11 fix: register the global notifier so any component can call
   * notify(...) from services/notify.ts instead of using window.alert().
   */
  useEffect(() => {
    registerNotifier((msg, type = 'info') => addNotification(msg, type));
    return () => registerNotifier(null);
  }, []);

  // Persist cart to server whenever it changes (debounced + skip during boot/logout).
  // CUSTOMERS persist their own cart; PHARMACY_MASTERS persist a multi-pharmacy cart
  // where each line carries onBehalfOfCustomerId.
  //
  // FE-21 fix: capture the user id at debounce-arm time and verify on fire that
  // we still have the same user. Without this, a debounced save scheduled before
  // logout would fire after logout and either 401 (silent) or save into the
  // wrong session if the user logged in as someone else within 400ms.
  useEffect(() => {
    if (isBooting || !user) return;
    if (user.role !== UserRole.CUSTOMER && user.role !== UserRole.PHARMACY_MASTER) return;
    const armedFor = user.id;
    const handle = setTimeout(() => {
      // Re-check user is still the same one we armed for
      if (!user || user.id !== armedFor) return;
      DataService.saveCart(cart.map(c => ({
        productId: c.product.id,
        quantity: c.quantity,
        onBehalfOfCustomerId: c.onBehalfOfCustomerId ?? user.id,
      })));
    }, 400);
    return () => clearTimeout(handle);
  }, [cart, isBooting, user]);

  /**
   * FE-15 fix: replace deprecated Math.random().toString(36).substr(...) with
   * crypto.randomUUID() (universal in modern browsers) and a fallback.
   */
  const generateId = (): string => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
      return crypto.randomUUID();
    }
    return `n_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 11)}`;
  };

  const addNotification = (message: string, type: 'success' | 'info' | 'warning' = 'info') => {
    const id = generateId();
    setNotifications(prev => [...prev, { id, message, type, timestamp: Date.now() }]);
  };

  const removeNotification = (id: string) => {
    setNotifications(prev => prev.filter(n => n.id !== id));
  };

  const handleLogin = (loggedInUser: User) => {
    setUser(loggedInUser);
    setActiveView('home');
    setProducts(DataService.getProducts());
    setOrders(DataService.getOrders());
    addNotification(`${t('welcome')} ${loggedInUser.name}`, 'success');
  };

  const handleLogout = async () => {
    await DataService.logout();
    setUser(null);
    setCart([]);
    setProducts([]);
    setOrders([]);
    setIsCartOpen(false);
    setIsBellOpen(false);
    setServerNotifications([]);
    setActiveView('home');
  };

  // Open / close the notification bell dropdown. On open, fetch the latest from /api/notifications.
  const toggleBell = async () => {
    const next = !isBellOpen;
    setIsBellOpen(next);
    if (next) {
      try {
        await DataService.refreshNotifications();
        setServerNotifications(DataService.getNotifications());
      } catch {/* non-fatal */}
    }
  };

  // FE-18 fix: bind to `click` (not `mousedown`) so a quick drag-select that
  // ends inside the bell dropdown doesn't accidentally close it. Also handle
  // touchstart for mobile.
  useEffect(() => {
    if (!isBellOpen) return;
    const handler = (e: MouseEvent | TouchEvent) => {
      if (bellRef.current && !bellRef.current.contains(e.target as Node)) {
        setIsBellOpen(false);
      }
    };
    document.addEventListener('click', handler);
    document.addEventListener('touchstart', handler);
    return () => {
      document.removeEventListener('click', handler);
      document.removeEventListener('touchstart', handler);
    };
  }, [isBellOpen]);

  // Background poll: refresh notifications every 15s while logged in so new ones show up
  useEffect(() => {
    if (!user) return;
    const tick = async () => {
      try {
        await DataService.refreshNotifications();
        setServerNotifications(DataService.getNotifications());
      } catch {/* ignore */}
    };
    tick();
    const id = setInterval(tick, 15000);
    return () => clearInterval(id);
  }, [user]);

  /**
   * FE-13 fix: route through DataService.markNotificationRead so the CSRF
   * cookie + credentials handling lives in one place (services/api.ts) rather
   * than being hand-rolled and duplicated in App.tsx.
   */
  const markNotificationRead = async (id: string) => {
    const r = await DataService.markNotificationRead(id);
    if (r.success) {
      setServerNotifications(prev => prev.map(n => n.id === id ? { ...n, readAt: new Date().toISOString() } : n));
    }
  };

  const unreadCount = serverNotifications.filter(n => !n.readAt).length;

  const handleAddToCart = (product: Product, quantity: number, customerId?: string) => {
    // Pharmacy Master flow: customerId targets one of the child pharmacies. Each
    // (product, customerId) pair is its own cart line so the master can have the
    // same product staged for different children with different quantities.
    const onBehalf = customerId ?? user?.id;
    const onBehalfName = customerId
      ? user?.childPharmacies?.find(p => p.id === customerId)?.name
      : undefined;

    setCart(prev => {
      const existing = prev.find(item =>
        item.product.id === product.id && (item.onBehalfOfCustomerId ?? user?.id) === onBehalf
      );
      if (existing) {
        return prev.map(item =>
          item.product.id === product.id && (item.onBehalfOfCustomerId ?? user?.id) === onBehalf
            ? { ...item, quantity: item.quantity + quantity }
            : item
        );
      }
      return [...prev, {
        product,
        quantity,
        onBehalfOfCustomerId: customerId,
        onBehalfOfCustomerName: onBehalfName,
      }];
    });
    setIsCartOpen(true);
    addNotification(t('item_added'), 'success');
  };

  const handleRemoveFromCart = (productId: string, customerId?: string) => {
    setCart(prev => prev.filter(item =>
      !(item.product.id === productId && (item.onBehalfOfCustomerId ?? null) === (customerId ?? null))
    ));
  };

  const handleUpdateCartQuantity = (productId: string, quantity: number, customerId?: string) => {
    setCart(prev => prev.map(item =>
      item.product.id === productId && (item.onBehalfOfCustomerId ?? null) === (customerId ?? null)
        ? { ...item, quantity }
        : item
    ));
  };

  const handleCheckout = async () => {
    if (!user) return;

    try {
      // Each cart line becomes one order via POST /api/orders.
      // For Pharmacy Masters, customerId is the child pharmacy id (apiClient.createOrder
      // forwards it as onBehalfOfCustomerId on the wire).
      for (const item of cart) {
        const customerId = item.onBehalfOfCustomerId ?? user.id;
        const customerName = item.onBehalfOfCustomerName ?? user.name;
        const order: Order = {
          id: '', orderNumber: '',
          productId: item.product.id,
          productName: item.product.name,
          customerId,
          customerName,
          supplierName: item.product.supplierName,
          quantity: item.quantity,
          unitOfMeasurement: item.product.unitOfMeasurement,
          status: OrderStatus.RECEIVED,
          date: new Date().toISOString(),
        };
        await DataService.createOrder(order);
      }
      setOrders(DataService.getOrders());
      setCart([]);
      addNotification('Orders placed successfully!', 'success');
      setActiveView(user.role === UserRole.CUSTOMER || user.role === UserRole.PHARMACY_MASTER ? 'my_requests' : 'orders');
    } catch {
      addNotification('Failed to place orders. Please try again.', 'warning');
    }
  };

  const handleUpdateOrderStatus = async (orderId: string, status: OrderStatus, note?: string) => {
    try {
      await DataService.updateOrderStatus(orderId, status, note);
      setOrders(DataService.getOrders());
      addNotification(`Order status updated to ${status}`, 'success');
    } catch {
      addNotification('Could not update order status.', 'warning');
    }
  };

  const handleUpdateOrder = async (orderId: string, updates: Partial<Order>, note?: string) => {
    try {
      await DataService.updateOrder(orderId, updates, note);
      setOrders(DataService.getOrders());
      addNotification('Order updated', 'success');
    } catch {
      addNotification('Could not update order.', 'warning');
    }
  };

  const handleUpdateProduct = async (updatedProduct: Product) => {
    try {
      await DataService.updateProduct(updatedProduct);
      setProducts(DataService.getProducts());
      addNotification('Product updated successfully', 'success');
    } catch {
      addNotification('Could not update product.', 'warning');
    }
  };

  const handleBulkAddProducts = async (newProducts: Product[]) => {
    try {
      for (const p of newProducts) await DataService.addProduct(p);
      setProducts(DataService.getProducts());
      addNotification(`${newProducts.length} products added successfully`, 'success');
    } catch {
      addNotification('Some products failed to add.', 'warning');
    }
  };

  const handleUpdateProfile = async (updatedUser: User) => {
    try {
      await DataService.updateUser(updatedUser);
      setUser(updatedUser);
      addNotification('Profile updated', 'success');
    } catch {
      addNotification('Could not update profile.', 'warning');
    }
  };

  const getNavItems = () => {
    if (!user) return [];
    
    const common = [{ id: 'home', label: t('nav_home'), icon: Home }];

    if (user.role === UserRole.CUSTOMER || user.role === UserRole.PHARMACY_MASTER) {
      const items = [
        ...common,
        { id: 'catalog', label: t('nav_catalog'), icon: LayoutGrid },
        { id: 'my_requests', label: t('nav_requests'), icon: Clock },
      ];
      // Buying Groups tab — masters can VIEW (they appear via their child memberships) but can't act
      // (locked decision #16). Customers can act normally.
      items.push({ id: 'buying_groups', label: t('nav_buying_groups'), icon: Users });
      // Phase D — Transfers + Agreements available to customers and masters.
      items.push({ id: 'transfers', label: t('nav_transfers'), icon: ArrowLeftRight });
      items.push({ id: 'agreements', label: t('nav_agreements'), icon: FileSignature });
      return items;
    }

    if (user.role === UserRole.SUPPLIER) {
      return [
        ...common,
        { id: 'supplier_orders', label: t('nav_orders'), icon: ShoppingBag },
        { id: 'supplier_partners', label: t('nav_partners'), icon: Globe },
        { id: 'transfers', label: t('nav_transfers'), icon: ArrowLeftRight },
        { id: 'agreements', label: t('nav_agreements'), icon: FileSignature },
        { id: 'supplier_reports', label: t('nav_reports'), icon: BarChart3 },
        { id: 'supplier_team', label: t('manage_team'), icon: Users },
      ];
    }

    if (user.role === UserRole.FOREIGN_SUPPLIER) {
      return [
        ...common,
        { id: 'foreign_dashboard', label: t('nav_dashboard'), icon: LayoutGrid },
        { id: 'foreign_reports', label: t('nav_reports'), icon: BarChart3 },
        { id: 'foreign_team', label: t('manage_team'), icon: Users },
      ];
    }

    if (user.role === UserRole.ADMIN) {
      return [
        ...common,
        { id: 'admin_portal', label: t('nav_admin'), icon: Settings },
        { id: 'transfers', label: t('nav_transfers'), icon: ArrowLeftRight },
        { id: 'agreements', label: t('nav_agreements'), icon: FileSignature },
      ];
    }

    return common;
  };

  const renderActiveView = () => {
    if (!user) return null;

    if (activeView === 'home') {
        return <Dashboard currentUser={user} orders={orders} products={products} />;
    }

    // Customer + Pharmacy Master share the same screens (master sees aggregate from all child pharmacies)
    if (user.role === UserRole.CUSTOMER || user.role === UserRole.PHARMACY_MASTER) {
        switch (activeView) {
            case 'catalog':
                return <CustomerPortal products={products} onRequestOrder={handleAddToCart} currentUser={user} orders={orders} onUpdateProfile={handleUpdateProfile} />;
            case 'my_requests':
                return <CustomerRequests orders={orders} currentUser={user} onUpdateOrder={handleUpdateOrder} />;
            case 'buying_groups':
                return <BuyingGroups currentUser={user} />;
            case 'transfers':
                return <Transfers currentUser={user} />;
            case 'agreements':
                return <PricingAgreements currentUser={user} />;
            default:
                return <Dashboard currentUser={user} orders={orders} products={products} />;
        }
    }

    // Supplier Views
    if (user.role === UserRole.SUPPLIER) {
        switch(activeView) {
            case 'transfers':
                return <Transfers currentUser={user} />;
            case 'agreements':
                return <PricingAgreements currentUser={user} />;
            case 'supplier_orders':
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        onUpdateOrder={handleUpdateOrder}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="orders"
                    />
                );
            case 'supplier_partners':
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="partners"
                    />
                );
            case 'supplier_reports':
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="reports"
                    />
                );
            case 'supplier_team':
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="team"
                    />
                );
            default:
                return <Dashboard currentUser={user} orders={orders} products={products} />;
        }
    }

    // Foreign Supplier Views
    if (user.role === UserRole.FOREIGN_SUPPLIER) {
        switch(activeView) {
            case 'transfers':
                return <Transfers currentUser={user} />;
            case 'agreements':
                return <PricingAgreements currentUser={user} />;
            case 'foreign_reports':
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        onUpdateOrder={handleUpdateOrder}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="reports"
                    />
                );
            case 'foreign_team':
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        onUpdateOrder={handleUpdateOrder}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="team"
                    />
                );
            default: // foreign_dashboard maps to 'orders' viewMode for reusability
                return (
                    <SupplierPortal 
                        orders={orders} 
                        onUpdateStatus={handleUpdateOrderStatus}
                        onUpdateOrder={handleUpdateOrder}
                        products={products}
                        onUpdateProduct={handleUpdateProduct}
                        onBulkAddProducts={handleBulkAddProducts}
                        currentUser={user}
                        onUpdateProfile={handleUpdateProfile}
                        viewMode="orders" 
                    />
                );
        }
    }

    // Admin Views
    if (user.role === UserRole.ADMIN) {
        if (activeView === 'transfers') return <Transfers currentUser={user} />;
        if (activeView === 'agreements') return <PricingAgreements currentUser={user} />;
        return <AdminPortal products={products} orders={orders} onUpdateProduct={handleUpdateProduct} />;
    }

    return <div>View not found</div>;
  };

  if (isBooting) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50" dir={dir}>
        <div className="flex flex-col items-center gap-3 text-slate-500">
          <div className="bg-teal-600 p-3 rounded-lg animate-pulse">
            <Activity className="h-6 w-6 text-white" />
          </div>
          <p className="text-sm font-medium">Loading EdgeRx…</p>
        </div>
      </div>
    );
  }

  if (isRegistering) {
    return <Register onNavigateToLogin={() => setIsRegistering(false)} />;
  }

  if (!user) {
    return <Login onLoginSuccess={handleLogin} onNavigateToRegister={() => setIsRegistering(true)} />;
  }

  return (
    <div className="min-h-screen bg-slate-50 font-sans text-slate-900" dir={dir}>
      {/* Navbar */}
      <nav className="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center gap-8">
              <div className="flex-shrink-0 flex items-center cursor-pointer" onClick={() => setActiveView('home')}>
                <img src="/logo-wide.png" alt="EdgeRx" className="h-9 w-auto" />
              </div>
              <div className="hidden md:flex space-x-1">
                {getNavItems().map(item => {
                    const Icon = item.icon;
                    return (
                        <button
                            key={item.id}
                            onClick={() => setActiveView(item.id)}
                            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all ${
                            activeView === item.id 
                                ? 'bg-slate-900 text-white shadow-lg shadow-slate-200' 
                                : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'
                            }`}
                        >
                            <Icon size={18} />
                            {item.label}
                        </button>
                    );
                })}
              </div>
            </div>
            <div className="flex items-center gap-3">
              <button onClick={toggleLanguage} className="p-2 text-gray-400 hover:text-teal-600 transition-colors font-bold text-xs uppercase">
                  {language === 'en' ? 'AR' : 'EN'}
              </button>
              
              <div className="relative" ref={bellRef}>
                  <button
                    type="button"
                    onClick={toggleBell}
                    className="p-2 text-gray-400 hover:text-teal-600 transition-colors relative"
                    aria-label={t('notifications_title')}
                    aria-expanded={isBellOpen}
                  >
                    <Bell className="h-6 w-6" />
                    {unreadCount > 0 && (
                      <span className="absolute top-0.5 right-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] text-[10px] font-bold leading-none text-white bg-red-500 rounded-full px-1 border-2 border-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                      </span>
                    )}
                  </button>

                  {isBellOpen && (
                    <div className="absolute right-0 rtl:right-auto rtl:left-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                      <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-gray-900">{t('notifications_title')}</h3>
                        {unreadCount > 0 && (
                          <button
                            type="button"
                            onClick={async () => {
                              // FE-13 fix: routed through DataService (single CSRF/cookie path)
                              await DataService.markAllNotificationsRead();
                              setServerNotifications(prev => prev.map(n => ({ ...n, readAt: n.readAt || new Date().toISOString() })));
                            }}
                            className="text-[11px] font-medium text-teal-600 hover:text-teal-700"
                          >
                            {t('mark_all_read')}
                          </button>
                        )}
                      </div>
                      <div className="max-h-96 overflow-y-auto">
                        {serverNotifications.length === 0 ? (
                          <div className="px-4 py-12 text-center text-sm text-gray-400">
                            <Bell className="h-8 w-8 mx-auto mb-2 opacity-30" />
                            {t('no_notifications')}
                          </div>
                        ) : (
                          serverNotifications.map((n) => {
                            const dot = n.type === 'success' ? 'bg-green-500'
                              : n.type === 'warning' ? 'bg-yellow-500'
                              : 'bg-blue-500';
                            return (
                              <button
                                key={n.id}
                                type="button"
                                onClick={() => !n.readAt && markNotificationRead(n.id)}
                                className={`w-full text-left px-4 py-3 border-b border-gray-50 last:border-b-0 hover:bg-gray-50 transition-colors flex items-start gap-3 ${n.readAt ? 'opacity-60' : ''}`}
                              >
                                <span className={`mt-1.5 h-2 w-2 rounded-full shrink-0 ${dot}`} />
                                <div className="flex-1 min-w-0">
                                  <p className="text-sm font-medium text-gray-900 leading-snug">{n.message}</p>
                                  <p className="text-[10px] text-gray-400 mt-1">
                                    {n.timestamp ? new Date(n.timestamp).toLocaleString() : ''}
                                  </p>
                                </div>
                              </button>
                            );
                          })
                        )}
                      </div>
                    </div>
                  )}
              </div>

              {(user.role === UserRole.CUSTOMER || user.role === UserRole.PHARMACY_MASTER) && (
                <button 
                  className="relative p-2 text-gray-400 hover:text-teal-600 transition-colors"
                  onClick={() => setIsCartOpen(true)}
                >
                  <ShoppingCart className="h-6 w-6" />
                  {cart.length > 0 && (
                    <span className="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-teal-600 rounded-full">
                      {cart.reduce((a, b) => a + b.quantity, 0)}
                    </span>
                  )}
                </button>
              )}
              
              <div className="h-8 w-px bg-gray-200 mx-1"></div>
              
              <div className="flex items-center gap-3 pl-1">
                  <div className="text-right hidden sm:block">
                      <p className="text-xs font-bold text-gray-900">{user.name}</p>
                      <p className="text-[10px] text-gray-500 uppercase tracking-wider">{user.role}</p>
                  </div>
                  <button onClick={handleLogout} className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" title={t('logout')}>
                    <LogOut size={20} />
                  </button>
              </div>
            </div>
          </div>
        </div>
        
        {/* Mobile Menu */}
        <div className="md:hidden border-t border-gray-100 overflow-x-auto">
            <div className="flex p-2 gap-2">
                {getNavItems().map(item => {
                    const Icon = item.icon;
                    return (
                        <button
                            key={item.id}
                            onClick={() => setActiveView(item.id)}
                            className={`flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold whitespace-nowrap ${
                            activeView === item.id 
                                ? 'bg-slate-900 text-white' 
                                : 'bg-white text-gray-600 border border-gray-200'
                            }`}
                        >
                            <Icon size={16} />
                            {item.label}
                        </button>
                    );
                })}
            </div>
        </div>
      </nav>

      <main className="pb-20">
        {renderActiveView()}
      </main>

      <CartDrawer 
        isOpen={isCartOpen} 
        onClose={() => setIsCartOpen(false)} 
        cart={cart}
        onRemove={handleRemoveFromCart}
        onUpdateQuantity={handleUpdateCartQuantity}
        onCheckout={handleCheckout}
      />

      <NotificationToast
        notifications={notifications}
        removeNotification={removeNotification}
      />

      {/* FE-11: branded confirm/alert replacement, mounted once globally */}
      <ConfirmDialog />
    </div>
  );
}
