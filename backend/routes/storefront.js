const express = require('express');
const StorefrontSetting = require('../models/storefrontSetting');

const router = express.Router();
const SETTING_KEY = 'storefront';
const DEFAULT_MESSAGE = 'Coming Soon — Discover a New Shopping Experience with FastXHorizon';

function publicAnnouncement(setting) {
  return {
    enabled: setting?.announcementEnabled ?? true,
    message: setting?.announcementMessage || DEFAULT_MESSAGE,
  };
}

router.get('/announcement', async (req, res, next) => {
  try {
    const setting = await StorefrontSetting.findOne({ key: SETTING_KEY }).lean();
    return res.json(publicAnnouncement(setting));
  } catch (error) {
    return next(error);
  }
});

module.exports = { router, SETTING_KEY, DEFAULT_MESSAGE, publicAnnouncement };
