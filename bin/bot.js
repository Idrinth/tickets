require('dotenv').config();
const { REST, Routes } = require('discord.js');
const { Client, GatewayIntentBits } = require('discord.js');
const needle = require('needle');

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
    if (interaction.user && interaction.user.tag) {
        const reply = await needle('post', `https://${process.env.SYSTEM_HOSTNAME}/api/new`, {
            key: process.env.BOT_API_KEY,
            user: interaction.user.tag,
            title: interaction.options.getString('subject'),
            description: interaction.options.getString('description'),
            private: interaction.options.getBoolean('private') ? 1 : 0
        });
        console.log(reply.body);
        const data = typeof reply.body === 'string' ? JSON.parse(reply.body) : reply.body;
        if (data.success) {
            await interaction.reply({content: `Created ticket at ${data.link}.`, ephemeral: interaction.options.getBoolean('private')});
            return;
        }
        await interaction.reply({content: "Failed to create ticket.", ephemeral: true});
        return;
    }
    await interaction.reply({content: "No user found, what happened?", ephemeral: true});
  }
});
client.login(process.env.DISCORD_TOKEN);