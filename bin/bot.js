require('dotenv').config();
const { REST, Routes } = require('discord.js');
const { Client, GatewayIntentBits } = require('discord.js');

const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

(async () => {
  try {
    console.log('Started refreshing application (/) commands.');

    await rest.put(Routes.applicationCommands(process.env.DISCORD_CLIENT_ID), { body: [
    {
      name: 'ticket',
      application_id: process.env.DISCORD_CLIENT_ID,
      description: `Creates a new ticket at ${process.env.SYSTEM_HOSTNAME}.`,
      options: [
          {
              name: 'subject',
              description: 'A short summary of the issue',
              type: 3,
              required: true,
          },
          {
              name: 'description',
              description: 'A detailed description of the issue',
              type: 3,
              required: true,
          },
          {
              name: 'type',
              description: 'The type of issue',
              type: 3,
              required: true,
              choices: [
                  {
                      name: 'Service Request',
                      value: 'service',
                  },
                  {
                      name: 'Bug',
                      value: 'bug',
                  },
                  {
                      name: 'Feature',
                      value: 'feature',
                  },
              ],
          },
          {
              name: 'unlisted',
              description: 'Should the issue be hidden?',
              type: 5,
              required: false,
          }
      ]
    },
  ]});

    console.log('Successfully reloaded application (/) commands.');
  } catch (error) {
    console.error(error);
  }
})();

const client = new Client({ intents: [GatewayIntentBits.Guilds] });

client.on('ready', () => {
  console.log(`Logged in as ${client.user.tag}!`);
});
client.on('interactionCreate', async interaction => {
  if (!interaction.isChatInputCommand()) {
      return;
  }

  if (interaction.commandName === 'ticket') {
    await interaction.reply('Pong!');
  }
});
client.login(process.env.DISCORD_TOKEN);