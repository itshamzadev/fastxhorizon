jest.mock('axios', () => ({ post: jest.fn() }));
jest.mock('../models/metaEvent', () => ({
  findOne: jest.fn(),
  create: jest.fn(),
  updateOne: jest.fn(),
}));

describe('Meta Conversions API delivery', () => {
  let axios;
  let MetaEvent;
  let sendMetaEvent;
  const previous = {};

  beforeAll(() => {
    for (const key of ['META_PIXEL_ID', 'META_CAPI_ACCESS_TOKEN', 'META_CAPI_ENABLED', 'META_CAPI_TEST_EVENT_CODE']) previous[key] = process.env[key];
    process.env.META_PIXEL_ID = '1318803496970561';
    process.env.META_CAPI_ACCESS_TOKEN = 'test-token';
    process.env.META_CAPI_ENABLED = 'true';
    process.env.META_CAPI_TEST_EVENT_CODE = 'TEST123';
    jest.resetModules();
    axios = require('axios');
    MetaEvent = require('../models/metaEvent');
    ({ sendMetaEvent } = require('../services/metaConversionsApi'));
  });

  afterAll(() => {
    for (const key of Object.keys(previous)) {
      if (previous[key] === undefined) delete process.env[key];
      else process.env[key] = previous[key];
    }
  });

  beforeEach(() => {
    MetaEvent.findOne.mockReturnValue({ lean: jest.fn().mockResolvedValue(null) });
    MetaEvent.create.mockResolvedValue({});
    MetaEvent.updateOne.mockResolvedValue({});
    axios.post.mockResolvedValue({ status: 200, data: { events_received: 1 } });
  });

  test('adds the optional test event code without exposing it elsewhere', async () => {
    await sendMetaEvent({ eventName: 'ViewContent', eventId: 'view:123', consent: true, customData: { value: 849 } });
    expect(axios.post).toHaveBeenCalledTimes(1);
    const payload = axios.post.mock.calls[0][1];
    expect(payload.test_event_code).toBe('TEST123');
    expect(payload.data[0].custom_data.currency).toBe('PKR');
  });

  test('does not send when consent is rejected', async () => {
    axios.post.mockClear();
    await sendMetaEvent({ eventName: 'ViewContent', eventId: 'view:denied', consent: false });
    expect(axios.post).not.toHaveBeenCalled();
  });
});
